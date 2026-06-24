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
 * Builds a standalone reader-safe HTML export of an entire campaign.
 *
 * The export is intended for later reading, not for debugging the application.
 * It therefore renders only the player-facing story transcript: player actions,
 * supplied dice results, and DM narrative responses. Normalized state tables,
 * raw prompts, context packs, and state delta JSON are intentionally omitted
 * because they may contain internal variables, inferred truths, or hidden state
 * that would spoil future campaign revelations.
 */
final class CampaignHtmlExportService
{
    /**
     * Store dependencies required to load campaign metadata and text files.
     */
    public function __construct(
        private FileStorage $storage,
        private CampaignRepository $campaigns,
        private SessionRepository $sessions,
        private PromptRepository $prompts,
        private EntityRepository $entities,
        private FactRepository $facts,
        private MysteryRepository $mysteries,
        private MarkdownRenderer $markdown
    ) {
    }

    /**
     * Build the full standalone HTML export for one campaign.
     */
    public function build(int $campaignId): string
    {
        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            throw new \RuntimeException('Campaign not found.');
        }

        $storyTurns = $this->sessions->listByCampaignAscending($campaignId);
        $storyTurnIds = array_map(static fn (array $turn): int => (int) $turn['id'], $storyTurns);
        $turnEvents = $this->sessions->listEventsForSessions($campaignId, $storyTurnIds);
        $description = $this->storage->read((string) ($campaign['description_file'] ?? ''));

        return '<!doctype html>' . "\n"
            . '<html lang="' . e((string) ($campaign['default_language'] ?? 'en')) . '">' . "\n"
            . '<head>' . $this->head((string) $campaign['title']) . '</head>' . "\n"
            . '<body>' . "\n"
            . '<main class="export-page">' . "\n"
            . $this->campaignHeader($campaign, count($storyTurns))
            . $this->descriptionSection($description)
            . $this->storySection($storyTurns, $turnEvents)
            . '</main>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * Build the <head> contents, including export-local CSS.
     */
    private function head(string $title): string
    {
        return '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>' . e($title) . ' campaign export</title>' . "\n"
            . '<style>' . $this->css() . '</style>' . "\n";
    }

    /**
     * Build the campaign title and reader-safe stats header.
     *
     * @param array<string, mixed> $campaign
     */
    private function campaignHeader(array $campaign, int $turnCount): string
    {
        $generatedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        return '<header class="hero">'
            . '<p class="eyebrow">Campaign export</p>'
            . '<h1>' . e((string) $campaign['title']) . '</h1>'
            . '<p class="subtitle">Generated ' . e($generatedAt) . '</p>'
            . '<dl class="stats">'
            . '<div><dt>Story turns</dt><dd>' . $turnCount . '</dd></div>'
            . '<div><dt>Export type</dt><dd>Readable story</dd></div>'
            . '</dl>'
            . '</header>' . "\n";
    }

    /**
     * Render campaign description when available.
     */
    private function descriptionSection(string $description): string
    {
        $description = trim($description);
        if ($description === '') {
            return '';
        }

        return '<section class="section-card">'
            . '<h2>Campaign description</h2>'
            . '<div class="rendered-markdown">' . $this->markdown->render($description) . '</div>'
            . '</section>' . "\n";
    }

    /**
     * Render the readable story transcript with player actions inline.
     *
     * @param array<int, array<string, mixed>> $storyTurns
     * @param array<int, array<int, array<string, mixed>>> $turnEvents
     */
    private function storySection(array $storyTurns, array $turnEvents): string
    {
        $html = '<section class="section-card story-section">'
            . '<div class="section-heading">'
            . '<p class="eyebrow">Readable transcript</p>'
            . '<h2>Story</h2>'
            . '<p class="muted">Player actions and DM responses are shown directly in reading order.</p>'
            . '</div>';

        if ($storyTurns === []) {
            return $html . '<p class="muted">No story turns have been stored yet.</p></section>' . "\n";
        }

        foreach ($storyTurns as $turn) {
            $turnId = (int) $turn['id'];
            $narrative = $this->storage->read((string) $turn['narrative_file']);
            $raw = $this->storage->read((string) $turn['raw_transcript_file']);
            $playerInput = $this->playerInputFromEvents($turnEvents[$turnId] ?? []);
            $playerAction = trim($playerInput['action']);
            $diceResults = trim($playerInput['dice']);

            $html .= '<article class="story-turn">'
                . '<header class="turn-header">'
                . '<p class="turn-number">Story turn ' . (int) $turn['sequence_number'] . '</p>'
                . '<h3>' . e((string) $turn['title']) . '</h3>'
                . '<p class="turn-date">' . e((string) $turn['created_at']) . '</p>'
                . '</header>';

            if ($playerAction !== '' || $diceResults !== '') {
                $html .= '<section class="speaker-card player-card">'
                    . '<p class="speaker-label">Player</p>';
                if ($playerAction !== '') {
                    $html .= '<div class="rendered-markdown player-text">' . $this->markdown->render($playerAction) . '</div>';
                }
                if ($diceResults !== '') {
                    $html .= '<div class="dice-result"><strong>Dice result:</strong><pre>' . e($diceResults) . '</pre></div>';
                }
                $html .= '</section>';
            }

            $html .= '<section class="speaker-card dm-card">'
                . '<p class="speaker-label">DM</p>'
                . '<div class="rendered-markdown narrative">' . $this->markdown->render($narrative !== '' ? $narrative : $raw) . '</div>'
                . '</section>'
                . '</article>';
        }

        return $html . '</section>' . "\n";
    }

    /**
     * Read stored player input events for one story turn.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array{action:string,dice:string}
     */
    private function playerInputFromEvents(array $events): array
    {
        $input = ['action' => '', 'dice' => ''];

        foreach ($events as $event) {
            $eventType = (string) ($event['event_type'] ?? '');
            if ($eventType !== 'player_action' && $eventType !== 'dice_result') {
                continue;
            }

            $text = trim($this->storage->read((string) ($event['description_file'] ?? '')));
            if ($text === '') {
                continue;
            }

            if ($eventType === 'player_action' && $input['action'] === '') {
                $input['action'] = $text;
            }
            if ($eventType === 'dice_result' && $input['dice'] === '') {
                $input['dice'] = $text;
            }
        }

        return $input;
    }

    /**
     * Inline CSS used by the standalone export.
     */
    private function css(): string
    {
        return <<<'CSS'
:root {
    color-scheme: light;
    --bg: #f6f4ef;
    --panel: #ffffff;
    --ink: #1f2933;
    --muted: #697586;
    --line: #d9dee7;
    --soft: #eef2f7;
    --accent: #3858e9;
    --accent-soft: #e7ebff;
    --player-bg: #f5f7ff;
    --dm-bg: #ffffff;
    --code-bg: #0b1220;
    --code-ink: #dbeafe;
    --code-border: #1e3a5f;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    line-height: 1.6;
}
.export-page {
    width: min(1120px, calc(100% - 32px));
    margin: 0 auto;
    padding: 32px 0 64px;
}
.hero,
.section-card,
.story-turn {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 24px;
    box-shadow: 0 18px 50px rgba(31, 41, 51, 0.08);
}
.hero {
    padding: 36px;
    margin-bottom: 24px;
}
.eyebrow {
    margin: 0 0 8px;
    color: var(--accent);
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}
h1,
h2,
h3,
h4 { line-height: 1.2; }
h1 { margin: 0; font-size: clamp(2rem, 5vw, 4.5rem); }
h2 { margin: 0 0 18px; font-size: 2rem; }
h3 { margin: 0 0 12px; font-size: 1.35rem; }
h4 { margin: 22px 0 8px; }
.subtitle,
.muted,
.meta,
.turn-date { color: var(--muted); }
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin: 28px 0 0;
}
.stats div {
    background: var(--soft);
    border-radius: 18px;
    padding: 16px;
}
.stats dt { color: var(--muted); font-size: 0.85rem; }
.stats dd { margin: 2px 0 0; font-size: 1.8rem; font-weight: 800; }
.section-card {
    padding: 28px;
    margin-bottom: 24px;
}
.section-heading { margin-bottom: 20px; }
.story-turn {
    padding: 26px;
    margin: 22px 0;
}
.turn-header {
    border-bottom: 1px solid var(--line);
    margin-bottom: 22px;
    padding-bottom: 16px;
}
.turn-number {
    margin: 0 0 6px;
    color: var(--accent);
    font-weight: 800;
}
.speaker-card {
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 18px 20px;
    margin: 18px 0;
}
.player-card {
    background: var(--player-bg);
    border-color: #cfd8ff;
}
.dm-card {
    background: var(--dm-bg);
}
.speaker-label {
    margin: 0 0 10px;
    color: var(--accent);
    font-size: 0.78rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}
.player-text {
    font-weight: 520;
}
.dice-result {
    margin-top: 14px;
}
.rendered-markdown p { margin: 0 0 1em; }
.rendered-markdown ul,
.rendered-markdown ol { padding-left: 1.4rem; }
.rendered-markdown li { margin: 0.28rem 0; }
.rendered-markdown blockquote {
    margin: 1.2rem 0;
    padding: 0.75rem 1rem;
    border-left: 4px solid var(--accent);
    background: var(--accent-soft);
    border-radius: 0 14px 14px 0;
}
.rendered-markdown code {
    background: var(--soft);
    border-radius: 6px;
    padding: 0.1rem 0.3rem;
}
pre {
    overflow: auto;
    white-space: pre-wrap;
    background: var(--code-bg);
    color: var(--code-ink);
    border: 1px solid var(--code-border);
    border-radius: 16px;
    padding: 16px;
    line-height: 1.55;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
}
pre code,
pre code * {
    background: transparent !important;
    color: var(--code-ink) !important;
    border: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    text-shadow: none !important;
}
pre code { display: block; font-size: 0.94rem; }
.readout-panel {
    border: 1px solid var(--line);
    background: #f8fafc;
    border-radius: 16px;
    padding: 16px;
    margin: 1.2rem 0;
}
.readout-title {
    font-weight: 800;
    margin-bottom: 8px;
}
.safety-section {
    background: #fbfcfe;
}
@media print {
    body { background: #fff; }
    .export-page { width: auto; padding: 0; }
    .hero,
    .section-card,
    .story-turn { box-shadow: none; break-inside: avoid; }
    .speaker-card { break-inside: avoid; }
}
CSS;
    }
}
