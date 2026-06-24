<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for campaign sessions and generated event indexes.
 */
final class SessionRepository extends BaseRepository
{
    /**
     * Create a session metadata row.
     */
    public function create(int $campaignId, string $title, string $rawFile, string $narrativeFile, ?string $deltaFile): int
    {
        $sequence = $this->nextSequence($campaignId);
        $now = $this->now();
        $stmt = $this->pdo()->prepare(
            'INSERT INTO sessions (campaign_id, sequence_number, title, raw_transcript_file, narrative_file, state_delta_file, status, created_at, updated_at)
             VALUES (:campaign_id, :sequence_number, :title, :raw_transcript_file, :narrative_file, :state_delta_file, :status, :created_at, :updated_at)'
        );
        $stmt->execute([
            'campaign_id' => $campaignId,
            'sequence_number' => $sequence,
            'title' => $title,
            'raw_transcript_file' => $rawFile,
            'narrative_file' => $narrativeFile,
            'state_delta_file' => $deltaFile,
            'status' => 'stored',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Create a session event index row.
     */
    public function createEvent(int $campaignId, int $sessionId, string $eventType, string $title, string $descriptionFile, string $visibility): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO session_events (campaign_id, session_id, event_type, title, description_file, visibility, created_at)
             VALUES (:campaign_id, :session_id, :event_type, :title, :description_file, :visibility, :created_at)'
        );
        $stmt->execute([
            'campaign_id' => $campaignId,
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'title' => $title,
            'description_file' => $descriptionFile,
            'visibility' => $visibility,
            'created_at' => $this->now(),
        ]);
    }

    /**
     * List recent sessions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $campaignId, int $limit = 10): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM sessions WHERE campaign_id = :campaign_id ORDER BY sequence_number DESC LIMIT ' . (int) $limit
        );
        $stmt->execute(['campaign_id' => $campaignId]);
        return $stmt->fetchAll();
    }

    /**
     * List all sessions for a campaign.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByCampaign(int $campaignId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM sessions WHERE campaign_id = :campaign_id ORDER BY sequence_number DESC');
        $stmt->execute(['campaign_id' => $campaignId]);
        return $stmt->fetchAll();
    }

    /**
     * Find one session belonging to a campaign.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $campaignId, int $sessionId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM sessions WHERE id = :id AND campaign_id = :campaign_id LIMIT 1');
        $stmt->execute(['id' => $sessionId, 'campaign_id' => $campaignId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Count sessions for a campaign.
     */
    public function countByCampaign(int $campaignId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM sessions WHERE campaign_id = :campaign_id');
        $stmt->execute(['campaign_id' => $campaignId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count all sessions.
     */
    public function countAll(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
    }

    /**
     * Return the next campaign-local session number.
     */
    private function nextSequence(int $campaignId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sessions WHERE campaign_id = :campaign_id');
        $stmt->execute(['campaign_id' => $campaignId]);
        return (int) $stmt->fetchColumn();
    }
}
