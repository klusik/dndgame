<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for campaign entities such as characters, ships, drones, and places.
 */
final class EntityRepository extends BaseRepository
{
    /**
     * Upsert an entity by canonical name.
     */
    public function upsert(int $campaignId, string $typeCode, string $name, string $visibility = 'player', string $status = 'active'): int
    {
        $existing = $this->findByName($campaignId, $name);
        if ($existing) {
            return (int) $existing['id'];
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO entities (campaign_id, type_code, canonical_name, display_name, visibility, status, created_at, updated_at)
             VALUES (:campaign_id, :type_code, :canonical_name, :display_name, :visibility, :status, :created_at, :updated_at)'
        );
        $now = $this->now();
        $stmt->execute([
            'campaign_id' => $campaignId,
            'type_code' => $typeCode,
            'canonical_name' => $name,
            'display_name' => $name,
            'visibility' => $visibility,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Find an entity by canonical name.
     *
     * @return array<string, mixed>|null
     */
    public function findByName(int $campaignId, string $name): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM entities WHERE campaign_id = :campaign_id AND canonical_name = :canonical_name LIMIT 1');
        $stmt->execute(['campaign_id' => $campaignId, 'canonical_name' => $name]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * List visible entities for a campaign.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByCampaign(int $campaignId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM entities WHERE campaign_id = :campaign_id ORDER BY type_code ASC, display_name ASC');
        $stmt->execute(['campaign_id' => $campaignId]);
        return $stmt->fetchAll();
    }

    /**
     * Count entities for a campaign.
     */
    public function countByCampaign(int $campaignId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM entities WHERE campaign_id = :campaign_id');
        $stmt->execute(['campaign_id' => $campaignId]);
        return (int) $stmt->fetchColumn();
    }
}
