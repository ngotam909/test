<?php
namespace Inc;

use Utils\Password;

final class UserService
{
    private const ROLES = ['user', 'admin'];

    public function __construct(private DB $db) {}

    public function create(string $name, string $email, string $password, string $role = 'user'): int
    {
        $role = in_array($role, self::ROLES, true) ? $role : 'user';

        $pdo  = $this->db->connect();
        $hash = Password::hash($password);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash, $role]);

        return (int) $pdo->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $pdo = $this->db->connect();
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        return $u ?: null;
    }

    public function verifyLogin(string $email, string $password): ?array
    {
        $pdo  = $this->db->connect();
        $user = $this->findByEmail($email);

        if (!$user || !Password::verify($password, $user['password_hash'])) {
            return null;
        }

        if (Password::needsRehash($user['password_hash'])) {
            $newHash = Password::hash($password);
            $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([$newHash, $user['id']]);
        }

        unset($user['password_hash']);
        return $user;
    }

    public function updateRole(int $userId, string $role): bool
    {
        if (!in_array($role, self::ROLES, true)) {
            return false;
        }
        $pdo = $this->db->connect();
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        return $stmt->execute([$role, $userId]);
    }
}
