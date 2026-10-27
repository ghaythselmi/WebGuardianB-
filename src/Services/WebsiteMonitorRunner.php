<?php

namespace App\Services;

use App\Entity\Website;
use App\Entity\WebsiteCheck;
use App\Repository\WebsiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WebsiteMonitorRunner
{
    public function __construct(
        private readonly WebsiteRepository $websites,
        private readonly WebsiteMonitorService $monitor,
        private readonly MonitorAlertMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(): int
    {
        $checked = 0;
        foreach ($this->websites->findAll() as $website) {
            try {
                $this->check($website);
                ++$checked;
            } catch (\Throwable $error) {
                $this->logger->error('Scheduled website monitor failed', ['website_id' => $website->getId(), 'exception' => $error]);
            }
        }
        $this->entityManager->flush();
        return $checked;
    }

    public function runOne(Website $website, bool $initialCheck = false): void
    {
        $this->check($website, $initialCheck);
        $this->entityManager->flush();
    }

    private function check(Website $website, bool $initialCheck = false): void
    {
        $url = (string) $website->getUrl();
        $availability = $this->monitor->checkAvailability($url);
        $ssl = $this->monitor->checkSslCertificate($url);
        $headers = $this->monitor->checkSecurityHeaders($url);
        $newState = $availability['status'];
        $oldState = $website->getAvailabilty();
        if ($initialCheck) {
            $website->setLastAlertedAvailability($newState);
        } elseif ($website->getLastAlertedAvailability() === null && in_array($oldState, ['Online', 'Offline'], true)) {
            $website->setLastAlertedAvailability($oldState);
        }
        $website->setAvailabilty($newState)
            ->setSSLStatus($ssl['status'])
            ->setExpiresIn((int) ($ssl['expires_in_days'] ?? 0))
            ->setLastCheck(new \DateTime())
            ->setCheckDetails($availability, $ssl, $headers);
        $this->entityManager->persist(new WebsiteCheck(
            $website, $newState, $availability['http_code'], $availability['response_time_ms'],
            $ssl['status'], $ssl['expires_in_days'] ?? null, $headers['score'], $headers['grade'],
            $availability['final_url'] ?? null, $availability['page_title'] ?? null,
            $ssl['issuer'] ?? null, $ssl['expires_at'] ?? null,
            $headers['present'] ?? [], $headers['missing'] ?? []
        ));

        if ($initialCheck) return;

        $user = $website->getUser();
        if (!$user) return;
        if (!$user->isEmailAlertsEnabled()) {
            $website->setLastAlertedAvailability($newState);
            return;
        }

        if ($newState !== $website->getLastAlertedAvailability() && in_array($newState, ['Online', 'Offline'], true)) {
            $subject = $newState === 'Offline' ? 'Website is offline' : 'Website is back online';
            $message = sprintf("%s is now %s.\nChecked: %s", $url, strtolower($newState), (new \DateTimeImmutable())->format(DATE_ATOM));
            $this->sendAlert($website, $subject, $message, function () use ($website, $newState): void { $website->setLastAlertedAvailability($newState); });
        }

        $days = $ssl['expires_in_days'];
        $level = match (true) { $days <= 1 => 1, $days <= 7 => 7, $days <= 30 => 30, default => null };
        if ($level !== null && $website->getLastSslAlertLevel() !== $level) {
            $message = sprintf("The SSL certificate for %s expires in %d day(s).", $url, $days);
            $this->sendAlert($website, 'SSL certificate expiry warning', $message, function () use ($website, $level): void { $website->setLastSslAlertLevel($level); });
        } elseif ($level === null && $website->getLastSslAlertLevel() !== null) {
            $website->setLastSslAlertLevel(null);
        }
    }

    private function sendAlert(Website $website, string $subject, string $message, callable $onSuccess): void
    {
        try {
            $this->mailer->send($website->getUser(), $website, $subject, $message);
            $onSuccess();
        } catch (\Throwable $error) {
            $this->logger->warning('Could not send WebGuardian alert', ['website_id' => $website->getId(), 'message' => $error->getMessage()]);
        }
    }
}
