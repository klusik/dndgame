<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CampaignRepository;
use App\Repositories\EntityRepository;
use App\Repositories\FactRepository;
use App\Repositories\MysteryRepository;
use App\Repositories\SessionRepository;

/**
 * Builds compact context packs for ChatGPT prompts.
 */
final class ContextPackService
{
    /**
     * Store dependencies required for context generation.
     */
    public function __construct(
        private FileStorage $storage,
        private CampaignRepository $campaigns,
        private EntityRepository $entities,
        private FactRepository $facts,
        private MysteryRepository $mysteries,
        private SessionRepository $sessions
    ) {
    }

    /**
     * Convert a locale code into a human-readable language name.
     */
    private function languageName(string $locale): string
    {
        return $locale === 'cs' ? 'Czech' : 'English';
    }

    /**
     * Build and persist the current context pack.
     */
    public function build(int $campaignId): string
    {
        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            throw new \RuntimeException('Campaign not found.');
        }

        $lines = [];
        $lines[] = '# Context pack: ' . $campaign['title'];
        $lines[] = '';
        $lines[] = '## Response language';
        $lines[] = '- Default campaign response language: ' . $this->languageName((string) $campaign['default_language']) . '.';
        $lines[] = '- Narrative and human-readable state delta values must follow this language unless the current prompt explicitly overrides it.';
        $lines[] = '';
        $lines[] = '## Operating rules';
        $lines[] = '- Act as a precise campaign continuity assistant and tabletop DM support tool.';
        $lines[] = '- Use only this campaign context. Do not import names, characters, locations, or plot facts from other campaigns, UI examples, previous unrelated chats, or memory.';
        $lines[] = '- Keep known facts, inferred facts, and hidden facts separated.';
        $lines[] = '- Do not reveal hidden or DM-only information in narrative text.';
        $lines[] = '- Preserve uncertainty. Never turn suspicion into confirmation without evidence.';
        $lines[] = '- Preserve player agency. Do not choose consequential player actions unless the current player action explicitly commits to them.';
        $lines[] = '- Use direct positive description. Avoid rhetorical negative contrast formulas such as not X but Y, not just X but Y, or stacked Not lines.';
        $lines[] = '- When a roll is requested, show the raw roll, all modifiers, and the final total.';
        $lines[] = '';
        $lines[] = '## Campaign description';
        $lines[] = trim($this->storage->read((string) $campaign['description_file'])) ?: 'No description saved yet.';
        $lines[] = '';

        $entityRows = $this->entities->listByCampaign($campaignId);
        $lines[] = '## Entities';
        if ($entityRows === []) {
            $lines[] = '- No indexed entities yet.';
        } else {
            foreach ($entityRows as $entity) {
                if (($entity['visibility'] ?? '') === 'dm_only') {
                    continue;
                }
                $lines[] = sprintf('- %s [%s, %s]', $entity['display_name'], $entity['type_code'], $entity['status']);
            }
        }
        $lines[] = '';

        $factRows = $this->facts->listCurrentByCampaign($campaignId, false);
        $lines[] = '## Current facts';
        if ($factRows === []) {
            $lines[] = '- No indexed facts yet.';
        } else {
            foreach ($factRows as $fact) {
                $subject = $fact['entity_name'] ?: $fact['subject_text'];
                $lines[] = sprintf(
                    '- [%s/%s] %s %s: %s',
                    $fact['category'],
                    $fact['certainty'],
                    $subject,
                    $fact['predicate'],
                    $fact['value_text']
                );
            }
        }
        $lines[] = '';

        $mysteryRows = $this->mysteries->listByCampaign($campaignId);
        $lines[] = '## Open mysteries';
        if ($mysteryRows === []) {
            $lines[] = '- No indexed mysteries yet.';
        } else {
            foreach ($mysteryRows as $mystery) {
                if (($mystery['visibility'] ?? '') === 'dm_only') {
                    continue;
                }
                $lines[] = sprintf('- [%s] %s', $mystery['status'], $mystery['title']);
            }
        }
        $lines[] = '';

        $recent = $this->sessions->recent($campaignId, 5);
        $lines[] = '## Recent session summaries';
        if ($recent === []) {
            $lines[] = '- No sessions imported yet.';
        } else {
            foreach (array_reverse($recent) as $session) {
                $summary = trim($this->storage->read((string) $session['narrative_file']));
                $summary = mb_substr($summary, 0, 1500);
                $lines[] = '### Session ' . $session['sequence_number'] . ': ' . $session['title'];
                $lines[] = $summary !== '' ? $summary : 'No narrative summary saved.';
                $lines[] = '';
            }
        }

        $content = implode("\n", $lines) . "\n";
        $path = (string) $campaign['storage_path'] . '/exports/context_pack_current.md';
        $this->storage->write($path, $content);

        return $content;
    }
}
