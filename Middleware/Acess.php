<?php
namespace Middleware;

use Inc\AuthService;
use Utils\Response;

class Access
{
    public static function requireLogin(): int
    {
        $u = AuthService::user();
        if (!$u || !isset($u['id'])) {
            Response::json(['error' => 'Unauthorized'], 401);
        }
        return (int) $u['id'];
    }

    public static function requireRole(string ...$roles): int
    {
        $u = AuthService::user();
        if (!$u || !isset($u['id'])) {
            Response::json(['error' => 'Unauthorized'], 401);
        }
        $role = $u['role'] ?? null;
        if ($roles && !in_array($role, $roles, true)) {
            Response::json(['error' => 'Forbidden'], 403);
        }
        return (int) $u['id'];
    }

    public static function isAdmin(): bool
    {
        return AuthService::role() === 'admin';
    }
}
