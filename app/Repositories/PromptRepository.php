<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for generated prompts, pasted responses, and pending deltas.
 */
final class PromptRepository extends BaseRepository
{
    /**
     * Store generated prompt metadata.
     */
    public function createPrompt(int $campaignId, ?int $sessionId, string $title, string $promptFile, string $hash, int $estimatedLength): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO generated_prompts (campaign_id, session_id, title, prompt_file, prompt_hash, estimated_length, created_at)
             VALUES (:campaign_id, :session_id, :title, :prompt_file, :prompt_hash, :estimated_length, :created_at)'
        );
        $stmt->execute([
            'campaign_id' => $campaignId,
            'session_id' => $sessionId,
            'title' => $title,
            'prompt_file' => $promptFile,
            'prompt_hash' => $hash,
            'estimated_length' => $estimatedLength,
            'created_at' => $this->now(),
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Find a generated prompt.
     *
     * @return array<string, mixed>|null
     */
    public function findPrompt(int $campaignId, int $promptId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM generated_prompts WHERE id = :id AND campaign_id = :campaign_id LIMIT 1');
        $stmt->execute(['id' => $promptId, 'campaign_id' => $campaignId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Store a pasted ChatGPT response metadata row.
     */
    public function createResponse(int $campaignId, ?int $promptId, string $responseFile, ?string $narrativeFile, ?string $deltaFile, string $status): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO chatgpt_responses (campaign_id, generated_prompt_id, response_file, narrative_file, delta_json_file, parse_status, created_at)
             VALUES (:campaign_id, :generated_prompt_id, :response_file, :narrative_file, :delta_json_file, :parse_status, :created_at)'
        );
        $stmt->execute([
            'campaign_id' => $campaignId,
            'generated_prompt_id' => $promptId,
            'response_file' => $responseFile,
            'narrative_file' => $narrativeFile,
            'delta_json_file' => $deltaFile,
            'parse_status' => $status,
            'created_at' => $this->now(),
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Store a pending state delta.
     */
    public function createDelta(int $campaignId, int $responseId, string $deltaFile): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO state_deltas (campaign_id, source_response_id, delta_file, status, created_at, updated_at)
             VALUES (:campaign_id, :source_response_id, :delta_file, :status, :created_at, :updated_at)'
        );
        $now = $this->now();
        $stmt->execute([
            'campaign_id' => $campaignId,
            'source_response_id' => $responseId,
            'delta_file' => $deltaFile,
            'status' => 'pending_review',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Find a state delta and response metadata.
     *
     * @return array<string, mixed>|null
     */
    public function findDelta(int $campaignId, int $deltaId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT sd.*, cr.response_file, cr.narrative_file, cr.generated_prompt_id
             FROM state_deltas sd
             INNER JOIN chatgpt_responses cr ON cr.id = sd.source_response_id
             WHERE sd.id = :id AND sd.campaign_id = :campaign_id LIMIT 1'
        );
        $stmt->execute(['id' => $deltaId, 'campaign_id' => $campaignId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }


    /**
     * Find the newest prompt that does not have any pasted ChatGPT response yet.
     *
     * Any stored response closes the waiting state. Even an unstructured response
     * is no longer treated as waiting, because it has already become part of the
     * campaign history as a narrative-only session.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestAwaitingResponse(int $campaignId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT gp.*
             FROM generated_prompts gp
             LEFT JOIN chatgpt_responses cr ON cr.generated_prompt_id = gp.id
             WHERE gp.campaign_id = :campaign_id AND cr.id IS NULL
             ORDER BY gp.created_at DESC, gp.id DESC
             LIMIT 1'
        );
        $stmt->execute(['campaign_id' => $campaignId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Find the newest unapplied state delta for a campaign.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestPendingDelta(int $campaignId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT sd.*, cr.generated_prompt_id, cr.narrative_file, cr.response_file
             FROM state_deltas sd
             INNER JOIN chatgpt_responses cr ON cr.id = sd.source_response_id
             WHERE sd.campaign_id = :campaign_id AND sd.status = :status
             ORDER BY sd.created_at DESC, sd.id DESC
             LIMIT 1'
        );
        $stmt->execute(['campaign_id' => $campaignId, 'status' => 'pending_review']);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * List generation history with prompt, response, delta, and applied session metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listGenerationTimeline(int $campaignId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT
                gp.id AS prompt_id,
                gp.title AS prompt_title,
                gp.prompt_file,
                gp.prompt_hash,
                gp.estimated_length,
                gp.created_at AS prompt_created_at,
                cr.id AS response_id,
                cr.response_file,
                cr.narrative_file,
                cr.delta_json_file,
                cr.parse_status,
                cr.created_at AS response_created_at,
                sd.id AS delta_id,
                sd.status AS delta_status,
                sd.created_at AS delta_created_at,
                sd.updated_at AS delta_updated_at,
                s.id AS session_id,
                s.sequence_number AS session_sequence_number,
                s.title AS session_title
             FROM generated_prompts gp
             LEFT JOIN chatgpt_responses cr ON cr.generated_prompt_id = gp.id
             LEFT JOIN state_deltas sd ON sd.source_response_id = cr.id
             LEFT JOIN sessions s ON s.id = sd.applied_session_id
             WHERE gp.campaign_id = :campaign_id
             ORDER BY gp.created_at DESC, gp.id DESC, cr.created_at DESC, cr.id DESC'
        );
        $stmt->execute(['campaign_id' => $campaignId]);
        $rows = $stmt->fetchAll();

        $orphanStatement = $this->pdo()->prepare(
            'SELECT
                NULL AS prompt_id,
                NULL AS prompt_title,
                NULL AS prompt_file,
                NULL AS prompt_hash,
                NULL AS estimated_length,
                NULL AS prompt_created_at,
                cr.id AS response_id,
                cr.response_file,
                cr.narrative_file,
                cr.delta_json_file,
                cr.parse_status,
                cr.created_at AS response_created_at,
                sd.id AS delta_id,
                sd.status AS delta_status,
                sd.created_at AS delta_created_at,
                sd.updated_at AS delta_updated_at,
                s.id AS session_id,
                s.sequence_number AS session_sequence_number,
                s.title AS session_title
             FROM chatgpt_responses cr
             LEFT JOIN state_deltas sd ON sd.source_response_id = cr.id
             LEFT JOIN sessions s ON s.id = sd.applied_session_id
             WHERE cr.campaign_id = :campaign_id AND cr.generated_prompt_id IS NULL
             ORDER BY cr.created_at DESC, cr.id DESC'
        );
        $orphanStatement->execute(['campaign_id' => $campaignId]);

        $combined = array_merge($rows, $orphanStatement->fetchAll());
        usort($combined, static function (array $left, array $right): int {
            $leftDate = (string) ($left['prompt_created_at'] ?? $left['response_created_at'] ?? '');
            $rightDate = (string) ($right['prompt_created_at'] ?? $right['response_created_at'] ?? '');
            return strcmp($rightDate, $leftDate);
        });

        return $combined;
    }


    /**
     * List generated prompts linked to applied story turns through responses and deltas.
     *
     * This is used by readable exports to recover player actions for older turns
     * that were created before player_action events were stored explicitly.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPromptInputsLinkedToSessions(int $campaignId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT
                s.id AS session_id,
                s.sequence_number AS session_sequence_number,
                s.created_at AS session_created_at,
                gp.id AS prompt_id,
                gp.prompt_file,
                gp.created_at AS prompt_created_at,
                cr.id AS response_id,
                cr.created_at AS response_created_at
             FROM sessions s
             INNER JOIN state_deltas sd ON sd.applied_session_id = s.id
             INNER JOIN chatgpt_responses cr ON cr.id = sd.source_response_id
             INNER JOIN generated_prompts gp ON gp.id = cr.generated_prompt_id
             WHERE s.campaign_id = :campaign_id
             ORDER BY s.sequence_number ASC, s.id ASC, gp.created_at ASC, gp.id ASC'
        );
        $stmt->execute(['campaign_id' => $campaignId]);
        return $stmt->fetchAll();
    }

    /**
     * List generated prompts in creation order for timestamp-based export fallback.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPromptsByCampaignAscending(int $campaignId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, campaign_id, session_id, title, prompt_file, prompt_hash, estimated_length, created_at
             FROM generated_prompts
             WHERE campaign_id = :campaign_id
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['campaign_id' => $campaignId]);
        return $stmt->fetchAll();
    }

    /**
     * Mark a delta as applied.
     */
    public function markDeltaApplied(int $deltaId, int $sessionId): void
    {
        $stmt = $this->pdo()->prepare('UPDATE state_deltas SET status = :status, applied_session_id = :session_id, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => 'applied',
            'session_id' => $sessionId,
            'updated_at' => $this->now(),
            'id' => $deltaId,
        ]);
    }
}
