<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for campaigns and memberships.
 */
final class CampaignRepository extends BaseRepository
{
    /**
     * Create a campaign owned by a user.
     */
    public function create(int $ownerUserId, string $title, string $descriptionFile, string $defaultLanguage): int
    {
        $slug = $this->makeSlug($title);
        $now = $this->now();

        $this->pdo()->beginTransaction();
        $stmt = $this->pdo()->prepare(
            'INSERT INTO campaigns (owner_user_id, title, slug, description_file, default_language, status, created_at, updated_at)
             VALUES (:owner_user_id, :title, :slug, :description_file, :default_language, :status, :created_at, :updated_at)'
        );
        $stmt->execute([
            'owner_user_id' => $ownerUserId,
            'title' => $title,
            'slug' => $slug,
            'description_file' => $descriptionFile,
            'default_language' => $defaultLanguage,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $campaignId = (int) $this->pdo()->lastInsertId();

        $storagePath = 'storage/campaigns/' . $campaignId . '-' . $slug;
        $update = $this->pdo()->prepare('UPDATE campaigns SET storage_path = :storage_path WHERE id = :id');
        $update->execute(['storage_path' => $storagePath, 'id' => $campaignId]);

        $membership = $this->pdo()->prepare(
            'INSERT INTO campaign_memberships (campaign_id, user_id, role, created_at)
             VALUES (:campaign_id, :user_id, :role, :created_at)'
        );
        $membership->execute([
            'campaign_id' => $campaignId,
            'user_id' => $ownerUserId,
            'role' => 'owner',
            'created_at' => $now,
        ]);
        $this->pdo()->commit();

        return $campaignId;
    }

    /**
     * Find a campaign accessible by a user.
     *
     * @return array<string, mixed>|null
     */
    public function findForUser(int $campaignId, int $userId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT c.* FROM campaigns c
             INNER JOIN campaign_memberships cm ON cm.campaign_id = c.id
             WHERE c.id = :id AND cm.user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['id' => $campaignId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Find a campaign by primary key.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $campaignId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM campaigns WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $campaignId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * List campaigns for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT c.* FROM campaigns c
             INNER JOIN campaign_memberships cm ON cm.campaign_id = c.id
             WHERE cm.user_id = :user_id
             ORDER BY c.updated_at DESC, c.title ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Count all campaigns.
     */
    public function countAll(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM campaigns')->fetchColumn();
    }

    /**
     * Touch campaign update timestamp.
     */
    public function touch(int $campaignId): void
    {
        $stmt = $this->pdo()->prepare('UPDATE campaigns SET updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['updated_at' => $this->now(), 'id' => $campaignId]);
    }

    /**
     * Generate a URL-safe slug.
     */
    private function makeSlug(string $title): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug));
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'campaign';
    }
}
