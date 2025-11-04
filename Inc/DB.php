<?php
namespace Inc;

use PDO;
use PDOException;
use const Config\DB as DB;

class DB
{
    private ?PDO $pdo = null;

    public function connect(): PDO
    {
        if ($this->pdo) return $this->pdo;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB['host'],
            DB['port'],
            DB['name'],
            DB['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->pdo = new PDO($dsn, DB['user'], DB['pass'], $options);
        return $this->pdo;
    }
}