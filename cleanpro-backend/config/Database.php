<?php

declare(strict_types=1);

namespace CleanPro\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbName = $_ENV['DB_NAME'] ?? 'cleanpro';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new PDOException("Connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private function __clone() {}
}
