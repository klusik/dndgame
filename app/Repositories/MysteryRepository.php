<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for mysteries and clues.
 */
final class MysteryRepository extends BaseRepository
{
    /**
     * Upsert a mystery by title.
     */
    public function upsert(int $campaignId, string $title, string $status = 'open', string $visibility = 'player'): int
    {
        $slug = $this->makeSlug($title);
        $existing = $this->findBySlug($campaignId, $slug);
        if ($existing) {
            return (int) $existing['id'];
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO mysteries (campaign_id, title, slug, status, visibility, created_at, updated_at)
             VALUES (:campaign_id, :title, :slug, :status, :visibility, :created_at, :updated_at)'
        );
        $now = $this->now();
        $stmt->execute([
            'campaign_id' => $campaignId,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'visibility' => $visibility,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Add a clue to a mystery.
     */
    public function addClue(int $mysteryId, ?int $sessionId, string $clueText, string $certainty, string $visibility): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO mystery_clues (mystery_id, session_id, clue_text, certainty, visibility, created_at)
             VALUES (:mystery_id, :session_id, :clue_text, :certainty, :visibility, :created_at)'
        );
        $stmt->execute([
            'mystery_id' => $mysteryId,
            'session_id' => $sessionId,
            'clue_text' => $clueText,
            'certainty' => $certainty,
            'visibility' => $visibility,
            'created_at' => $this->now(),
        ]);
    }

    /**
     * List mysteries for a campaign.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByCampaign(int $campaignId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM mysteries WHERE campaign_id = :campaign_id ORDER BY status ASC, title ASC');
        $stmt->execute(['campaign_id' => $campaignId]);
        return $stmt->fetchAll();
    }

    /**
     * List clues for a group of mysteries grouped by mystery ID.
     *
     * @param array<int, int> $mysteryIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function listCluesForMysteries(array $mysteryIds): array
    {
        $mysteryIds = array_values(array_unique(array_filter(array_map('intval', $mysteryIds), static fn (int $id): bool => $id > 0)));
        if ($mysteryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($mysteryIds), '?'));
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM mystery_clues WHERE mystery_id IN (' . $placeholders . ') ORDER BY mystery_id ASC, created_at ASC, id ASC'
        );
        $stmt->execute($mysteryIds);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['mystery_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * Count mysteries for a campaign.
     */
    public function countByCampaign(int $campaignId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM mysteries WHERE campaign_id = :campaign_id');
        $stmt->execute(['campaign_id' => $campaignId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Find a mystery by slug.
     *
     * @return array<string, mixed>|null
     */
    private function findBySlug(int $campaignId, string $slug): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM mysteries WHERE campaign_id = :campaign_id AND slug = :slug LIMIT 1');
        $stmt->execute(['campaign_id' => $campaignId, 'slug' => $slug]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Generate a safe slug.
     */
    private function makeSlug(string $title): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug));
        return trim($slug, '-') ?: 'mystery';
    }
}
