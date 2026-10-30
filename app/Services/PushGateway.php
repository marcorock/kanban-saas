<?php

declare(strict_types=1);
namespace App\Services;
interface PushGateway
{
    /** Retorna o status HTTP do serviço de push, sem expor endpoint ou credenciais. */
    public function send(array $subscription, array $payload): int;
}
