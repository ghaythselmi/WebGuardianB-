<?php

namespace App\Services;

use App\Entity\User;
use App\Entity\Website;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

class MonitorAlertMailer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly KernelInterface $kernel,
    ) {}

    public function sendPasswordReset(User $user, string $resetUrl): void
    {
        $mail = $this->createMail();
        $mail->addAddress((string) $user->getEmail(), (string) $user->getUsername());
        $mail->Subject = 'Reset your WebGuardian password';
        $context = ['username' => (string) $user->getUsername(), 'resetUrl' => $resetUrl, 'expiresInMinutes' => 60];
        $mail->isHTML(true);
        $mail->Body = $this->twig->render('emails/password_reset.html.twig', $context);
        $mail->AltBody = $this->twig->render('emails/password_reset.txt.twig', $context);
        $mail->send();
    }

    public function send(User $user, Website $website, string $subject, string $message): void
    {
        $mail = $this->createMail();
        $mail->addAddress((string) $user->getEmail(), (string) $user->getUsername());
        $mail->Subject = $subject;
        $context = $this->buildEmailContext($website, $subject, $message);
        $mail->isHTML(true);
        $mail->Body = $this->twig->render('emails/website_alert.html.twig', $context);
        $mail->AltBody = $this->twig->render('emails/website_alert.txt.twig', $context);

        $logoPath = $this->kernel->getProjectDir().'/public/images/webguardian-neon-shield-mark.png';
        if (is_file($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'webguardian-shield', 'webguardian-neon-shield-mark.png', 'base64', 'image/png');
        }
        $mail->send();
    }

    private function createMail(): PHPMailer
    {
        $from = $this->env('MAIL_FROM_ADDRESS', 'webguardianweb@gmail.com');
        $mail = new PHPMailer(true);
        $mail->Timeout = 10;
        $mail->isSMTP();
        $mail->Host = $this->env('MAIL_HOST', 'smtp.gmail.com');
        $mail->Port = (int) $this->env('MAIL_PORT', '587');
        $username = $this->env('MAIL_USERNAME', $from);
        if ($username !== '') {
            $password = $this->env('MAIL_PASSWORD', '');
            if ($password === '') throw new \RuntimeException('Set MAIL_PASSWORD to configure email delivery.');
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
        }
        $encryption = strtolower($this->env('MAIL_ENCRYPTION', 'tls'));
        if ($encryption === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        elseif ($encryption === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        elseif ($encryption !== 'none') throw new \RuntimeException('MAIL_ENCRYPTION must be tls, ssl, or none.');
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, $this->env('MAIL_FROM_NAME', 'WebGuardian'));
        return $mail;
    }

    private function buildEmailContext(Website $website, string $subject, string $message): array
    {
        $normalizedSubject = strtolower($subject);
        $isOffline = str_contains($normalizedSubject, 'offline') || str_contains($normalizedSubject, 'down');
        $isRecovery = str_contains($normalizedSubject, 'online') || str_contains($normalizedSubject, 'recover');
        $isSslWarning = str_contains($normalizedSubject, 'ssl') || str_contains($normalizedSubject, 'certificate');

        if ($isOffline) {
            $kind = 'offline';
            $headline = 'Your website is offline';
            $statusLabel = 'Offline';
            $intro = 'The latest WebGuardian check marked this website as offline.';
            $nextStep = 'Check your hosting or origin server, DNS settings, and recent deployments. WebGuardian will alert you when the site responds again.';
            $tone = '#fb7185';
            $toneBackground = 'rgba(251, 113, 133, .12)';
        } elseif ($isRecovery) {
            $kind = 'recovered';
            $headline = 'Your website is back online';
            $statusLabel = 'Online';
            $intro = 'A recent WebGuardian check can reach your website again.';
            $nextStep = 'Your visitors should be able to reach the site. You may want to verify important pages, forms, and services after the interruption.';
            $tone = '#4adea0';
            $toneBackground = 'rgba(74, 222, 160, .12)';
        } elseif ($isSslWarning) {
            $kind = 'certificate';
            $headline = 'Your SSL certificate needs attention';
            $statusLabel = 'Certificate warning';
            $intro = 'WebGuardian found that this website’s SSL certificate is nearing its expiration date.';
            $nextStep = 'Renew the certificate before it expires to help prevent browser security warnings and HTTPS connection errors.';
            $tone = '#fbbf24';
            $toneBackground = 'rgba(251, 191, 36, .12)';
        } else {
            $kind = 'general';
            $headline = 'Website monitoring alert';
            $statusLabel = 'Attention needed';
            $intro = $message;
            $nextStep = 'Review this website’s current health and take any action needed.';
            $tone = '#43d9ed';
            $toneBackground = 'rgba(67, 217, 237, .12)';
        }

        $lastCheck = $website->getLastCheck();
        $checkedAt = $lastCheck
            ? \DateTimeImmutable::createFromInterface($lastCheck)->setTimezone(new \DateTimeZone('UTC'))->format('M j, Y · H:i:s \U\T\C')
            : 'Not available';
        $httpCode = $website->getHttpStatusCode();
        $responseDetail = $httpCode !== null
            ? sprintf('HTTP %d response', $httpCode)
            : ($isOffline ? 'No HTTP response received' : 'Not available');

        return [
            'kind' => $kind,
            'headline' => $headline,
            'statusLabel' => $statusLabel,
            'intro' => $intro,
            'nextStep' => $nextStep,
            'tone' => $tone,
            'toneBackground' => $toneBackground,
            'websiteUrl' => (string) $website->getUrl(),
            'checkedAt' => $checkedAt,
            'responseDetail' => $responseDetail,
            'message' => $message,
        ];
    }

    private function env(string $name, string $default): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
