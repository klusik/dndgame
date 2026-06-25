<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EntityRepository;
use App\Repositories\FactRepository;
use App\Repositories\MysteryRepository;
use App\Repositories\SessionRepository;

/**
 * Builds compact reader-facing campaign information for the sticky side panel.
 */
final class CampaignSidePanelService
{
    /**
     * Store repository and storage dependencies used to build the panel data.
     */
    public function __construct(
        private EntityRepository $entities,
        private FactRepository $facts,
        private MysteryRepository $mysteries,
        private SessionRepository $sessions,
        private FileStorage $storage
    ) {
    }

    /**
     * Build all tab data for the side panel.
     *
     * @return array<string, mixed>
     */
    public function build(int $campaignId): array
    {
        $entities = $this->visibleRows($this->entities->listByCampaign($campaignId));
        $facts = $this->visibleRows($this->facts->listCurrentByCampaign($campaignId, false));
        $mysteries = $this->visibleRows($this->mysteries->listByCampaign($campaignId));
        $clues = $this->mysteries->listCluesForMysteries(array_map(static fn (array $row): int => (int) $row['id'], $mysteries));
        $recentTurns = $this->hydrateRecentTurns($this->sessions->recent($campaignId, 6));

        return [
            'characters' => $this->characterRows($entities, $facts),
            'skills' => $this->skillRows($facts),
            'resources' => $this->resourceRows($facts),
            'objectives' => $this->objectiveRows($facts, $mysteries, $clues),
            'recentTurns' => $recentTurns,
            'mapsAvailable' => false,
        ];
    }

    /**
     * Remove hidden rows from any repository result list.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function visibleRows(array $rows): array
    {
        return array_values(array_filter($rows, static function (array $row): bool {
            return (string) ($row['visibility'] ?? 'player') !== 'dm_only';
        }));
    }

    /**
     * Build character summary rows from known entities and character facts.
     *
     * @param array<int, array<string, mixed>> $entities
     * @param array<int, array<string, mixed>> $facts
     * @return array<int, array<string, mixed>>
     */
    private function characterRows(array $entities, array $facts): array
    {
        $characterTypes = ['character', 'pc', 'player', 'npc', 'companion'];
        $characters = [];

        foreach ($entities as $entity) {
            $type = strtolower((string) ($entity['type_code'] ?? 'other'));
            if (!in_array($type, $characterTypes, true)) {
                continue;
            }

            $characters[(string) $entity['display_name']] = [
                'name' => (string) $entity['display_name'],
                'type' => (string) $entity['type_code'],
                'status' => (string) $entity['status'],
                'facts' => [],
            ];
        }

        foreach ($facts as $fact) {
            if (strtolower((string) ($fact['category'] ?? '')) !== 'character') {
                continue;
            }

            $subject = trim((string) ($fact['entity_name'] ?: $fact['subject_text']));
            if ($subject === '') {
                $subject = 'Character';
            }

            $characters[$subject] ??= [
                'name' => $subject,
                'type' => 'character',
                'status' => 'known',
                'facts' => [],
            ];

            if (count($characters[$subject]['facts']) < 8) {
                $characters[$subject]['facts'][] = [
                    'label' => $this->humanize((string) $fact['predicate']),
                    'value' => (string) $fact['value_text'],
                    'certainty' => (string) $fact['certainty'],
                ];
            }
        }

        return array_slice(array_values($characters), 0, 8);
    }

    /**
     * Build skill and attribute rows from indexed facts.
     *
     * @param array<int, array<string, mixed>> $facts
     * @return array<int, array<string, string>>
     */
    private function skillRows(array $facts): array
    {
        $rows = [];
        $needles = ['skill', 'proficiency', 'attribute', 'modifier', 'level', 'hp', 'health', 'strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma', 'technology', 'hacking'];

        foreach ($facts as $fact) {
            $text = strtolower((string) ($fact['category'] . ' ' . $fact['subject_text'] . ' ' . $fact['predicate']));
            if (!$this->containsAny($text, $needles)) {
                continue;
            }

            $rows[] = [
                'subject' => (string) ($fact['entity_name'] ?: $fact['subject_text']),
                'label' => $this->humanize((string) $fact['predicate']),
                'value' => (string) $fact['value_text'],
            ];

            if (count($rows) >= 12) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Build compact resource and equipment rows from facts.
     *
     * @param array<int, array<string, mixed>> $facts
     * @return array<int, array<string, string>>
     */
    private function resourceRows(array $facts): array
    {
        $rows = [];
        $resourceCategories = ['item', 'equipment', 'resource', 'vehicle', 'system'];

        foreach ($facts as $fact) {
            $category = strtolower((string) ($fact['category'] ?? ''));
            if (!in_array($category, $resourceCategories, true)) {
                continue;
            }

            $rows[] = [
                'subject' => (string) ($fact['entity_name'] ?: $fact['subject_text']),
                'label' => $this->humanize((string) $fact['predicate']),
                'value' => (string) $fact['value_text'],
            ];

            if (count($rows) >= 10) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Build journal objective rows from objective facts and active mysteries.
     *
     * @param array<int, array<string, mixed>> $facts
     * @param array<int, array<string, mixed>> $mysteries
     * @param array<int, array<int, array<string, mixed>>> $clues
     * @return array<int, array<string, mixed>>
     */
    private function objectiveRows(array $facts, array $mysteries, array $clues): array
    {
        $rows = [];
        $objectiveCategories = ['objective', 'journal', 'job', 'quest', 'goal', 'task'];

        foreach ($facts as $fact) {
            $category = strtolower((string) ($fact['category'] ?? ''));
            if (!in_array($category, $objectiveCategories, true)) {
                continue;
            }

            $rows[] = [
                'kind' => 'objective',
                'title' => (string) $fact['subject_text'],
                'status' => $this->humanize((string) $fact['predicate']),
                'summary' => (string) $fact['value_text'],
                'certainty' => (string) $fact['certainty'],
            ];
        }

        foreach ($mysteries as $mystery) {
            $status = strtolower((string) ($mystery['status'] ?? 'open'));
            if (in_array($status, ['closed', 'resolved'], true)) {
                continue;
            }

            $latestClue = $this->latestVisibleClue($clues[(int) $mystery['id']] ?? []);
            $rows[] = [
                'kind' => 'mystery',
                'title' => (string) $mystery['title'],
                'status' => (string) $mystery['status'],
                'summary' => $latestClue,
                'certainty' => 'tracked',
            ];
        }

        return array_slice($rows, 0, 12);
    }

    /**
     * Return the newest visible clue text for a mystery.
     *
     * @param array<int, array<string, mixed>> $clues
     */
    private function latestVisibleClue(array $clues): string
    {
        for ($index = count($clues) - 1; $index >= 0; $index--) {
            if ((string) ($clues[$index]['visibility'] ?? 'player') === 'dm_only') {
                continue;
            }
            return (string) ($clues[$index]['clue_text'] ?? '');
        }

        return '';
    }

    /**
     * Read compact recent story turn excerpts.
     *
     * @param array<int, array<string, mixed>> $turns
     * @return array<int, array<string, string|int>>
     */
    private function hydrateRecentTurns(array $turns): array
    {
        $rows = [];
        foreach ($turns as $turn) {
            $text = trim($this->storage->read((string) ($turn['narrative_file'] ?? '')));
            $text = preg_replace('/\s+/', ' ', strip_tags($text)) ?: $text;
            $rows[] = [
                'id' => (int) $turn['id'],
                'sequence' => (int) $turn['sequence_number'],
                'title' => (string) $turn['title'],
                'excerpt' => mb_strlen($text) > 180 ? mb_substr($text, 0, 180) . ' [truncated]' : $text,
            ];
        }

        return $rows;
    }

    /**
     * Check whether a lowercase haystack contains any lowercase needle.
     *
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert machine labels into compact readable labels.
     */
    private function humanize(string $value): string
    {
        $value = trim(str_replace(['_', '-'], ' ', $value));
        return $value === '' ? 'Status' : mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
