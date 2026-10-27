<?php

namespace App\Services;

use App\Entity\Website;
use App\Entity\WebsiteCheck;
use App\Entity\UserNotification;
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

    public function runOne(Website $website, bool $initialCheck = false, bool $notifyIfOffline = false): void
    {
        $this->check($website, $initialCheck, $notifyIfOffline);
        $this->entityManager->flush();
    }

    private function check(Website $website, bool $initialCheck = false, bool $notifyIfOffline = false): void
    {
        $url = (string) $website->getUrl();
        $availability = $this->monitor->checkAvailability($url);
        $ssl = $this->monitor->checkSslCertificate($url);
        $headers = $this->monitor->checkSecurityHeaders($url);
        $newState = $availability['status'];
        $oldState = $website->getAvailabilty();
        if ($initialCheck && $newState !== 'Offline') {
            $website->setLastAlertedAvailability($newState);
        } elseif (!$initialCheck && $website->getLastAlertedAvailability() === null && $oldState === 'Online') {
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

        if ($initialCheck) {
            if ($newState === 'Offline') $this->notifyAvailability($website, $newState, true);
            return;
        }

        $user = $website->getUser();
        if (!$user) return;
        $stateChanged = $newState !== $website->getLastAlertedAvailability();
        if (in_array($newState, ['Online', 'Offline'], true) && ($stateChanged || ($notifyIfOffline && $newState === 'Offline'))) {
            $this->notifyAvailability($website, $newState, $notifyIfOffline && $newState === 'Offline');
        }

        if (!$user->isEmailAlertsEnabled()) return;

        $days = $ssl['expires_in_days'];
        $level = match (true) { $days <= 1 => 1, $days <= 7 => 7, $days <= 30 => 30, default => null };
        if ($level !== null && $website->getLastSslAlertLevel() !== $level) {
            $message = sprintf("The SSL certificate for %s expires in %d day(s).", $url, $days);
            $this->sendAlert($website, 'SSL certificate expiry warning', $message, function () use ($website, $level): void { $website->setLastSslAlertLevel($level); });
        } elseif ($level === null && $website->getLastSslAlertLevel() !== null) {
            $website->setLastSslAlertLevel(null);
        }
    }

    private function notifyAvailability(Website $website, string $state, bool $forceNotification = false): void
    {
        $user = $website->getUser();
        if (!$user) return;

        $url = (string) $website->getUrl();
        $isOffline = $state === 'Offline';
        $type = $isOffline ? 'website_down' : 'website_recovered';
        $title = $isOffline ? 'Website is down' : 'Website is back online';
        $message = sprintf('%s is now %s.', $url, strtolower($state));
        $latestNotification = $this->entityManager->getRepository(UserNotification::class)->findOneBy(
            ['user' => $user, 'websiteUrl' => $url], ['id' => 'DESC']
        );
        if ($forceNotification || !$latestNotification || $latestNotification->getType() !== $type) {
            $this->entityManager->persist(new UserNotification($user, $type, $title, $message, $url));
        }

        if ($user->isEmailAlertsEnabled()) {
            $emailMessage = sprintf("%s\nChecked: %s", $message, (new \DateTimeImmutable())->format(DATE_ATOM));
            $subject = $isOffline ? 'WebGuardian alert: website is down' : 'WebGuardian alert: website is back online';
            $this->sendAlert($website, $subject, $emailMessage, function () use ($website, $state): void { $website->setLastAlertedAvailability($state); });
        } else {
            $website->setLastAlertedAvailability($state);
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
