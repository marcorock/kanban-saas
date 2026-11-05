<?php

declare(strict_types=1);

namespace App\Policies;

final class WorkspacePolicy
{
    public static function isOwner(array $workspace, int $userId): bool
    {
        return self::isMember($workspace, $userId) && (int) $workspace['owner_id'] === $userId;
    }

    public static function isMember(array $workspace, int $userId): bool
    {
        return $userId > 0 && (int) ($workspace['user_id'] ?? 0) === $userId
            && in_array($workspace['role'] ?? null, ['admin', 'member'], true);
    }

    public static function can(array $workspace, int $userId, string $action, ?array $target = null, string $role = 'member'): bool
    {
        if (!self::isMember($workspace, $userId)) return false;
        $owner = self::isOwner($workspace, $userId);
        $manager = $owner || $workspace['role'] === 'admin';
        return match ($action) {
            'create_project' => true,
            'rename', 'transfer_ownership' => $owner,
            'invite' => $manager && ($role === 'member' || ($owner && $role === 'admin')),
            'change_role', 'remove_member' => $target !== null
                && (int) ($target['id'] ?? 0) === (int) $workspace['id']
                && self::isMember($target, (int) ($target['user_id'] ?? 0))
                && (int) $target['user_id'] !== (int) $workspace['owner_id']
                && ($action !== 'change_role' || in_array($role, ['admin', 'member'], true))
                && ($owner || ($manager && $target['role'] === 'member' && $role === 'member')),
            default => false,
        };
    }
}
