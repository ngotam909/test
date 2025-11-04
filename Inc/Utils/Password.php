<?php
namespace Utils;

class Password
{
    private const COST = 12;

    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    public static function verify(string $plain, string $hashed): bool
    {
        return password_verify($plain, $hashed);
    }

    public static function needsRehash(string $hashed): bool
    {
        return password_needs_rehash($hashed, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }
}