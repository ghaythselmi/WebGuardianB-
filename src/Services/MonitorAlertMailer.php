<?php

namespace App\Services;

use App\Entity\User;
use App\Entity\Website;
use PHPMailer\PHPMailer\PHPMailer;

class MonitorAlertMailer
{
    public function send(User $user, Website $website, string $subject, string $message): void
    {
        $host = getenv('MAIL_HOST') ?: '';
        $from = getenv('MAIL_FROM_ADDRESS') ?: '';
        if ($host === '' || $from === '') {
            throw new \RuntimeException('Email alerts require MAIL_HOST and MAIL_FROM_ADDRESS environment settings.');
        }

        $mail = new PHPMailer(true);
        $mail->Timeout = 10;
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) (getenv('MAIL_PORT') ?: 587);
        $username = getenv('MAIL_USERNAME') ?: '';
        if ($username !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = getenv('MAIL_PASSWORD') ?: '';
        }
        $encryption = strtolower(getenv('MAIL_ENCRYPTION') ?: 'tls');
        if ($encryption === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        elseif ($encryption === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        elseif ($encryption !== 'none') throw new \RuntimeException('MAIL_ENCRYPTION must be tls, ssl, or none.');
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, getenv('MAIL_FROM_NAME') ?: 'WebGuardian');
        $mail->addAddress((string) $user->getEmail(), (string) $user->getUsername());
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = $message;
        $mail->send();
    }
}
