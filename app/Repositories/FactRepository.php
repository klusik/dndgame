<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for atomic campaign facts.
 */
final class FactRepository extends BaseRepository
{
    /**
     * Create a fact row.
     */
    public function create(
        int $campaignId,
        ?int $subjectEntityId,
        string $category,
        string $subjectText,
        string $predicate,
        string $valueText,
        string $certainty,
        string $visibility,
        ?int $sourceSessionId
    ): int {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO facts (campaign_id, subject_entity_id, category, subject_text, predicate, value_text, certainty, visibility, source_session_id, is_current, created_at, updated_at)
             VALUES (:campaign_id, :subject_entity_id, :category, :subject_text, :predicate, :value_text, :certainty, :visibility, :source_session_id, 1, :created_at, :updated_at)'
        );
        $now = $this->now();
        $stmt->execute([
            'campaign_id' => $campaignId,
            'subject_entity_id' => $subjectEntityId,
            'category' => $category,
            'subject_text' => $subjectText,
            'predicate' => $predicate,
            'value_text' => $valueText,
            'certainty' => $certainty,
            'visibility' => $visibility,
            'source_session_id' => $sourceSessionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * List current facts for context generation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCurrentByCampaign(int $campaignId, bool $includeDmOnly = false): array
    {
        $sql = 'SELECT f.*, e.display_name AS entity_name FROM facts f
                LEFT JOIN entities e ON e.id = f.subject_entity_id
                WHERE f.campaign_id = :campaign_id AND f.is_current = 1';
        if (!$includeDmOnly) {
            $sql .= " AND f.visibility <> 'dm_only'";
        }
        $sql .= ' ORDER BY f.category ASC, f.subject_text ASC, f.id ASC';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['campaign_id' => $campaignId]);
        return $stmt->fetchAll();
    }

    /**
     * Count facts for a campaign.
     */
    public function countByCampaign(int $campaignId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM facts WHERE campaign_id = :campaign_id');
        $stmt->execute(['campaign_id' => $campaignId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count all facts.
     */
    public function countAll(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM facts')->fetchColumn();
    }
}
