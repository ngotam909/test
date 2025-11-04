<?php
namespace Inc;

use Utils\Password;

final class AuthService
{
    public function __construct(private DB $db) {}

    public function login(string $email, string $password): bool
    {
        $pdo = $this->db->connect();
        $stmt = $pdo->prepare('SELECT id,name,email,password_hash,role FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !Password::verify($password, $user['password_hash'])) {
            return false;
        }

        if (Password::needsRehash($user['password_hash'])) {
            $new = Password::hash($password);
            $upd = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $upd->execute([$new, $user['id']]);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['auth'] = [
            'id'    => (int)$user['id'],
            'email' => $user['email'],
            'name'  => $user['name'],
            'role'  => $user['role'],
        ];
        return true;
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        unset($_SESSION['auth']);
    }

    public static function user(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION['auth'] ?? null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role'] ?? null;
    }
}
