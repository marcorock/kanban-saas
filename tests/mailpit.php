<?php

declare(strict_types=1);

$mailpit = getenv('MAILPIT_TEST_URL') ?: 'http://127.0.0.1:8025';
function messagesFor(string $email): array {
    global $mailpit;
    $data = json_decode(file_get_contents($mailpit . '/api/v1/messages?limit=100'), true, flags: JSON_THROW_ON_ERROR);
    return array_values(array_filter($data['messages'], static fn (array $message): bool => ($message['To'][0]['Address'] ?? '') === $email));
}
function mailToken(string $email, string $path): string {
    global $mailpit;
    $messages = messagesFor($email);
    check(count($messages) > 0, 'E-mail SMTP não recebido');
    $message = json_decode(file_get_contents($mailpit . '/api/v1/message/' . $messages[0]['ID']), true, flags: JSON_THROW_ON_ERROR);
    check((bool) preg_match('~' . preg_quote($path, '~') . '\?token=([a-f0-9]{64})~', $message['Text'], $match), 'Link não encontrado no e-mail');
    return $match[1];
}
