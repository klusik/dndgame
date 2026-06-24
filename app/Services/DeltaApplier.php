<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CampaignRepository;
use App\Repositories\EntityRepository;
use App\Repositories\FactRepository;
use App\Repositories\MysteryRepository;
use App\Repositories\PromptRepository;
use App\Repositories\SessionRepository;

/**
 * Applies reviewed JSON deltas to the normalized campaign indexes.
 */
final class DeltaApplier
{
    /**
     * Store dependencies used during delta application.
     */
    public function __construct(
        private FileStorage $storage,
        private CampaignRepository $campaigns,
        private SessionRepository $sessions,
        private EntityRepository $entities,
        private FactRepository $facts,
        private MysteryRepository $mysteries,
        private PromptRepository $prompts,
        private CampaignFileService $files,
        private ContextPackService $contextPacks
    ) {
    }

    /**
     * Apply a pending delta and create a new story turn record.
     */
    public function apply(int $campaignId, int $deltaId): int
    {
        $deltaRow = $this->prompts->findDelta($campaignId, $deltaId);
        if (!$deltaRow) {
            throw new \RuntimeException('State delta not found.');
        }
        if ($deltaRow['status'] !== 'pending_review') {
            throw new \RuntimeException('State delta has already been processed.');
        }

        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            throw new \RuntimeException('Campaign not found.');
        }

        $deltaRaw = $this->storage->read((string) $deltaRow['delta_file']);
        $delta = json_decode($deltaRaw, true);
        if (!is_array($delta)) {
            throw new \RuntimeException('Stored delta JSON is invalid.');
        }

        $title = trim((string) ($delta['turn_title'] ?? $delta['session_title'] ?? 'Imported ChatGPT response'));
        $title = $title !== '' ? $title : 'Imported ChatGPT response';
        $narrativeFile = (string) $deltaRow['narrative_file'];
        $responseFile = (string) $deltaRow['response_file'];
        $sessionId = $this->sessions->create($campaignId, $title, $responseFile, $narrativeFile, (string) $deltaRow['delta_file']);

        $this->applyEntities($campaignId, $delta);
        $this->applyFacts($campaignId, $sessionId, $delta);
        $this->applyMysteries($campaignId, $sessionId, $delta);
        $this->applyEvents($campaign, $campaignId, $sessionId, $delta);

        $this->prompts->markDeltaApplied($deltaId, $sessionId);
        $this->campaigns->touch($campaignId);
        $this->files->refreshMirrors($campaignId);
        $this->contextPacks->build($campaignId);

        return $sessionId;
    }

    /**
     * Apply entity changes.
     *
     * @param array<string, mixed> $delta
     */
    private function applyEntities(int $campaignId, array $delta): void
    {
        foreach ($this->arrayItems($delta['entities'] ?? []) as $entity) {
            $name = trim((string) ($entity['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $this->entities->upsert(
                $campaignId,
                (string) ($entity['type'] ?? 'other'),
                $name,
                (string) ($entity['visibility'] ?? 'player'),
                (string) ($entity['status'] ?? 'active')
            );
        }
    }

    /**
     * Apply fact changes.
     *
     * @param array<string, mixed> $delta
     */
    private function applyFacts(int $campaignId, int $sessionId, array $delta): void
    {
        foreach ($this->arrayItems($delta['facts'] ?? []) as $fact) {
            $subject = trim((string) ($fact['subject'] ?? ''));
            $predicate = trim((string) ($fact['predicate'] ?? ''));
            $value = trim((string) ($fact['value'] ?? ''));
            if ($subject === '' || $predicate === '' || $value === '') {
                continue;
            }

            $entity = $this->entities->findByName($campaignId, $subject);
            $this->facts->create(
                $campaignId,
                $entity ? (int) $entity['id'] : null,
                (string) ($fact['category'] ?? 'other'),
                $subject,
                $predicate,
                $value,
                (string) ($fact['certainty'] ?? 'confirmed'),
                (string) ($fact['visibility'] ?? 'player'),
                $sessionId
            );
        }
    }

    /**
     * Apply mysteries and clues.
     *
     * @param array<string, mixed> $delta
     */
    private function applyMysteries(int $campaignId, int $sessionId, array $delta): void
    {
        foreach ($this->arrayItems($delta['mysteries'] ?? []) as $mystery) {
            $title = trim((string) ($mystery['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $mysteryId = $this->mysteries->upsert(
                $campaignId,
                $title,
                (string) ($mystery['status'] ?? 'open'),
                (string) ($mystery['visibility'] ?? 'player')
            );
            foreach ($this->arrayItems($mystery['clues'] ?? []) as $clue) {
                $text = trim((string) ($clue['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $this->mysteries->addClue(
                    $mysteryId,
                    $sessionId,
                    $text,
                    (string) ($clue['certainty'] ?? 'confirmed'),
                    (string) ($clue['visibility'] ?? 'player')
                );
            }
        }
    }

    /**
     * Store event files and event metadata.
     *
     * @param array<string, mixed> $campaign
     * @param array<string, mixed> $delta
     */
    private function applyEvents(array $campaign, int $campaignId, int $sessionId, array $delta): void
    {
        $index = 1;
        foreach ($this->arrayItems($delta['events'] ?? []) as $event) {
            $title = trim((string) ($event['title'] ?? 'Event'));
            $description = trim((string) ($event['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $path = sprintf('%s/events/session-%04d-event-%02d.md', $campaign['storage_path'], $sessionId, $index);
            $this->storage->write($path, '# ' . $title . "\n\n" . $description . "\n");
            $this->sessions->createEvent(
                $campaignId,
                $sessionId,
                (string) ($event['type'] ?? 'event'),
                $title,
                $path,
                (string) ($event['visibility'] ?? 'player')
            );
            $index++;
        }
    }

    /**
     * Return only array items from a mixed value.
     *
     * @return array<int, array<string, mixed>>
     */
    private function arrayItems(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }
}
