<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * PDO connection factory for MariaDB and MySQL.
 */
final class Database
{
    private Config $config;
    private ?PDO $pdo = null;

    /**
     * Store configuration for lazy connection creation.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Return a configured PDO connection.
     */
    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $host = (string) $this->config->get('db.host');
        $port = (int) $this->config->get('db.port', 3306);
        $database = (string) $this->config->get('db.database');
        $charset = (string) $this->config->get('db.charset', 'utf8mb4');
        $username = (string) $this->config->get('db.username');
        $password = (string) $this->config->get('db.password');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new PDOException('Database connection failed: ' . $exception->getMessage(), (int) $exception->getCode());
        }

        return $this->pdo;
    }
}
