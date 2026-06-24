<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Base repository exposing shared PDO access.
 */
abstract class BaseRepository
{
    /**
     * Store the database service.
     */
    public function __construct(protected Database $database)
    {
    }

    /**
     * Return PDO for concrete repositories.
     */
    protected function pdo(): PDO
    {
        return $this->database->pdo();
    }

    /**
     * Return a SQL datetime string.
     */
    protected function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
