<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public function __construct(private array $config) {}

    public function send(string $to, string $subject, string $body): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Host = $this->config['host'];
        $mail->Port = $this->config['port'];
        $mail->SMTPAuth = $this->config['username'] !== '';
        $mail->Username = $this->config['username'];
        $mail->Password = $this->config['password'];
        if (!in_array($this->config['encryption'], ['tls', 'ssl', 'none'], true)) {
            throw new \InvalidArgumentException('Criptografia SMTP inválida.');
        }
        $mail->SMTPSecure = $this->config['encryption'] === 'none' ? '' : $this->config['encryption'];
        $mail->SMTPAutoTLS = $this->config['encryption'] !== 'none';
        $mail->Timeout = 10;
        $mail->getSMTPInstance()->Timelimit = 15;
        $mail->setFrom($this->config['from_address'], $this->config['from_name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    }
}
