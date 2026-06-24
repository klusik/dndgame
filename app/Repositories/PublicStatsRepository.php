<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for public anonymous statistics.
 */
final class PublicStatsRepository extends BaseRepository
{
    /**
     * Return aggregate counts for the public page.
     *
     * @return array<string, int|string|null>
     */
    public function getStats(): array
    {
        return [
            'campaigns' => (int) $this->pdo()->query('SELECT COUNT(*) FROM campaigns')->fetchColumn(),
            'sessions' => (int) $this->pdo()->query('SELECT COUNT(*) FROM sessions')->fetchColumn(),
            'facts' => (int) $this->pdo()->query('SELECT COUNT(*) FROM facts')->fetchColumn(),
            'last_activity' => $this->pdo()->query('SELECT MAX(updated_at) FROM campaigns')->fetchColumn() ?: null,
        ];
    }
}
