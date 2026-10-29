<?php

declare(strict_types=1);

namespace App\Policies;

use Pecee\SimpleRouter\Exceptions\HttpException;

final class ProjectPolicy
{
    public static function canManage(array $project, int $userId): bool
    {
        return $project['role'] === 'admin' || self::isOwner($project, $userId);
    }

    public static function isOwner(array $project, int $userId): bool
    {
        return $project['role'] === 'owner' && (int) $project['owner_id'] === $userId;
    }

    public static function authorizeMembers(array $project, int $userId): void
    {
        if (!self::canManage($project, $userId)) { throw new HttpException('Permissão insuficiente.', 403); }
        if ($project['archived_at'] !== null) { throw new HttpException('Restaure o projeto antes de gerenciar participantes.', 409); }
    }

    public static function authorize(array $project, int $userId, string $action): void
    {
        $allowed = $action === 'update' ? self::canManage($project, $userId) : self::isOwner($project, $userId);
        if (!$allowed) { throw new HttpException('Permissão insuficiente.', 403); }
        if ($action === 'update' && $project['archived_at'] !== null) {
            throw new HttpException('Restaure o projeto antes de editar.', 409);
        }
    }
}
