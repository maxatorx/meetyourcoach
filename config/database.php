<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbname = $_ENV['DB_NAME'] ?? 'meetyourcoach';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $port = (int) ($_ENV['DB_PORT'] ?? 3306);

        $legacyConfig = __DIR__ . '/config.inc.php';
        if (file_exists($legacyConfig)) {
            /** @psalm-suppress UnresolvableInclude */
            require $legacyConfig;
            if (isset($server)) {
                $host = (string) $server;
            }
            if (isset($database)) {
                $dbname = (string) $database;
            }
            if (isset($user)) {
                $username = (string) $user;
            }
            if (isset($passwd)) {
                $password = (string) $passwd;
            }
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
        }

        return self::$connection;
    }
}
