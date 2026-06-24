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
 * Builds a standalone readable HTML export of an entire campaign.
 *
 * The export intentionally contains inline CSS and no JavaScript so the file can
 * be archived, sent to another person, or opened outside the application. The
 * main story is rendered as readable prose, while raw prompts, responses, and
 * state deltas are preserved in collapsible archive sections.
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
        $turnEvents = $this->sessions->listEventsForSessions($campaignId, $this->ids($storyTurns));
        $generationTimeline = array_reverse($this->prompts->listGenerationTimeline($campaignId));
        $entityRows = $this->entities->listByCampaign($campaignId);
        $factRows = $this->facts->listCurrentByCampaign($campaignId, true);
        $mysteryRows = $this->mysteries->listByCampaign($campaignId);
        $clues = $this->mysteries->listCluesForMysteries($this->ids($mysteryRows));
        $description = $this->storage->read((string) ($campaign['description_file'] ?? ''));

        return '<!doctype html>' . "\n"
            . '<html lang="' . e((string) ($campaign['default_language'] ?? 'en')) . '">' . "\n"
            . '<head>' . $this->head((string) $campaign['title']) . '</head>' . "\n"
            . '<body>' . "\n"
            . '<main class="export-page">' . "\n"
            . $this->campaignHeader($campaign, count($storyTurns), count($factRows), count($entityRows), count($mysteryRows))
            . $this->descriptionSection($description)
            . $this->storySection($storyTurns, $turnEvents)
            . $this->stateSection($entityRows, $factRows, $mysteryRows, $clues)
            . $this->archiveSection($generationTimeline)
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
     * Build the campaign title and stats header.
     *
     * @param array<string, mixed> $campaign
     */
    private function campaignHeader(array $campaign, int $turnCount, int $factCount, int $entityCount, int $mysteryCount): string
    {
        $generatedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        return '<header class="hero">'
            . '<p class="eyebrow">Campaign export</p>'
            . '<h1>' . e((string) $campaign['title']) . '</h1>'
            . '<p class="subtitle">Generated ' . e($generatedAt) . '</p>'
            . '<dl class="stats">'
            . '<div><dt>Story turns</dt><dd>' . $turnCount . '</dd></div>'
            . '<div><dt>Facts</dt><dd>' . $factCount . '</dd></div>'
            . '<div><dt>Entities</dt><dd>' . $entityCount . '</dd></div>'
            . '<div><dt>Mysteries</dt><dd>' . $mysteryCount . '</dd></div>'
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
     * Render the readable story transcript.
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
            . '</div>';

        if ($storyTurns === []) {
            return $html . '<p class="muted">No story turns have been stored yet.</p></section>' . "\n";
        }

        foreach ($storyTurns as $turn) {
            $turnId = (int) $turn['id'];
            $narrative = $this->storage->read((string) $turn['narrative_file']);
            $raw = $this->storage->read((string) $turn['raw_transcript_file']);
            $delta = $this->storage->read((string) ($turn['state_delta_file'] ?? ''));

            $html .= '<article class="story-turn">'
                . '<header class="turn-header">'
                . '<p class="turn-number">Story turn ' . (int) $turn['sequence_number'] . '</p>'
                . '<h3>' . e((string) $turn['title']) . '</h3>'
                . '<p class="turn-date">' . e((string) $turn['created_at']) . '</p>'
                . '</header>'
                . '<div class="rendered-markdown narrative">' . $this->markdown->render($narrative !== '' ? $narrative : $raw) . '</div>'
                . $this->eventList($turnEvents[$turnId] ?? []);

            if ($raw !== '' || $delta !== '') {
                $html .= '<details class="archive-details">'
                    . '<summary>Raw stored data for this turn</summary>'
                    . ($raw !== '' ? '<h4>Raw ChatGPT response</h4><pre>' . e($raw) . '</pre>' : '')
                    . ($delta !== '' ? '<h4>State delta JSON</h4><pre>' . e($delta) . '</pre>' : '')
                    . '</details>';
            }

            $html .= '</article>';
        }

        return $html . '</section>' . "\n";
    }

    /**
     * Render event summaries stored for a story turn.
     *
     * @param array<int, array<string, mixed>> $events
     */
    private function eventList(array $events): string
    {
        if ($events === []) {
            return '';
        }

        $html = '<details class="event-details"><summary>Indexed events</summary><ul>';
        foreach ($events as $event) {
            $description = trim($this->storage->read((string) $event['description_file']));
            $html .= '<li><strong>' . e((string) $event['title']) . '</strong>';
            if ($description !== '') {
                $html .= '<div class="rendered-markdown small-markdown">' . $this->markdown->render($description) . '</div>';
            }
            $html .= '</li>';
        }
        return $html . '</ul></details>';
    }

    /**
     * Render current normalized campaign state.
     *
     * @param array<int, array<string, mixed>> $entities
     * @param array<int, array<string, mixed>> $facts
     * @param array<int, array<string, mixed>> $mysteries
     * @param array<int, array<int, array<string, mixed>>> $clues
     */
    private function stateSection(array $entities, array $facts, array $mysteries, array $clues): string
    {
        return '<section class="section-card state-section">'
            . '<div class="section-heading">'
            . '<p class="eyebrow">Campaign memory</p>'
            . '<h2>Current indexed state</h2>'
            . '</div>'
            . $this->entitiesTable($entities)
            . $this->factsTable($facts)
            . $this->mysteryList($mysteries, $clues)
            . '</section>' . "\n";
    }

    /**
     * Render the entity table.
     *
     * @param array<int, array<string, mixed>> $entities
     */
    private function entitiesTable(array $entities): string
    {
        if ($entities === []) {
            return '<h3>Entities</h3><p class="muted">No entities indexed.</p>';
        }

        $rows = '';
        foreach ($entities as $entity) {
            $rows .= '<tr><td>' . e((string) $entity['display_name']) . '</td>'
                . '<td>' . e((string) $entity['type_code']) . '</td>'
                . '<td>' . e((string) $entity['status']) . '</td>'
                . '<td>' . e((string) $entity['visibility']) . '</td></tr>';
        }

        return '<h3>Entities</h3><div class="table-wrap"><table><thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Visibility</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div>';
    }

    /**
     * Render current facts.
     *
     * @param array<int, array<string, mixed>> $facts
     */
    private function factsTable(array $facts): string
    {
        if ($facts === []) {
            return '<h3>Facts</h3><p class="muted">No facts indexed.</p>';
        }

        $rows = '';
        foreach ($facts as $fact) {
            $rows .= '<tr><td>' . e((string) $fact['category']) . '</td>'
                . '<td>' . e((string) $fact['subject_text']) . '</td>'
                . '<td>' . e((string) $fact['predicate']) . '</td>'
                . '<td>' . e((string) $fact['value_text']) . '</td>'
                . '<td>' . e((string) $fact['certainty']) . '</td>'
                . '<td>' . e((string) $fact['visibility']) . '</td></tr>';
        }

        return '<h3>Facts</h3><div class="table-wrap"><table><thead><tr><th>Category</th><th>Subject</th><th>Predicate</th><th>Value</th><th>Certainty</th><th>Visibility</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div>';
    }

    /**
     * Render mysteries and their clues.
     *
     * @param array<int, array<string, mixed>> $mysteries
     * @param array<int, array<int, array<string, mixed>>> $clues
     */
    private function mysteryList(array $mysteries, array $clues): string
    {
        if ($mysteries === []) {
            return '<h3>Mysteries</h3><p class="muted">No mysteries indexed.</p>';
        }

        $html = '<h3>Mysteries</h3><div class="mystery-list">';
        foreach ($mysteries as $mystery) {
            $mysteryId = (int) $mystery['id'];
            $html .= '<article class="mystery-card">'
                . '<h4>' . e((string) $mystery['title']) . '</h4>'
                . '<p class="meta">Status: ' . e((string) $mystery['status']) . ' · Visibility: ' . e((string) $mystery['visibility']) . '</p>';
            if (!empty($clues[$mysteryId])) {
                $html .= '<ul>';
                foreach ($clues[$mysteryId] as $clue) {
                    $html .= '<li>' . e((string) $clue['clue_text']) . ' <span class="meta">(' . e((string) $clue['certainty']) . ')</span></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    /**
     * Render prompt and response archive.
     *
     * @param array<int, array<string, mixed>> $timeline
     */
    private function archiveSection(array $timeline): string
    {
        $html = '<section class="section-card archive-section">'
            . '<div class="section-heading">'
            . '<p class="eyebrow">Technical archive</p>'
            . '<h2>Prompts and responses</h2>'
            . '<p class="muted">This section preserves the raw prompt workflow. It is collapsed by default because it is useful for auditing, not for normal reading.</p>'
            . '</div>';

        if ($timeline === []) {
            return $html . '<p class="muted">No generated prompts or pasted responses stored.</p></section>' . "\n";
        }

        foreach ($timeline as $entry) {
            $title = (string) ($entry['prompt_title'] ?? $entry['session_title'] ?? 'Workflow item');
            $prompt = $this->storage->read((string) ($entry['prompt_file'] ?? ''));
            $response = $this->storage->read((string) ($entry['response_file'] ?? ''));
            $narrative = $this->storage->read((string) ($entry['narrative_file'] ?? ''));
            $delta = $this->storage->read((string) ($entry['delta_json_file'] ?? ''));

            $html .= '<details class="archive-details">'
                . '<summary>' . e($title) . '</summary>'
                . '<dl class="archive-meta">'
                . '<div><dt>Prompt</dt><dd>' . e((string) ($entry['prompt_created_at'] ?? 'n/a')) . '</dd></div>'
                . '<div><dt>Response</dt><dd>' . e((string) ($entry['response_created_at'] ?? 'n/a')) . '</dd></div>'
                . '<div><dt>Parse status</dt><dd>' . e((string) ($entry['parse_status'] ?? 'n/a')) . '</dd></div>'
                . '</dl>'
                . ($prompt !== '' ? '<h4>Generated prompt</h4><pre>' . e($prompt) . '</pre>' : '')
                . ($response !== '' ? '<h4>Pasted response</h4><pre>' . e($response) . '</pre>' : '')
                . ($narrative !== '' ? '<h4>Extracted narrative</h4><div class="rendered-markdown">' . $this->markdown->render($narrative) . '</div>' : '')
                . ($delta !== '' ? '<h4>State delta JSON</h4><pre>' . e($delta) . '</pre>' : '')
                . '</details>';
        }

        return $html . '</section>' . "\n";
    }

    /**
     * Return integer IDs from database rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, int>
     */
    private function ids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
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
    --code-bg: #111827;
    --code-ink: #e5e7eb;
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
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
    border-radius: 16px;
    padding: 16px;
    line-height: 1.45;
}
pre code { background: transparent; padding: 0; }
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
details {
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 14px 16px;
    margin-top: 18px;
    background: #fbfcfe;
}
summary {
    cursor: pointer;
    font-weight: 800;
}
.table-wrap { overflow-x: auto; }
table {
    width: 100%;
    border-collapse: collapse;
    margin: 12px 0 28px;
    font-size: 0.93rem;
}
th,
td {
    border-bottom: 1px solid var(--line);
    padding: 10px;
    text-align: left;
    vertical-align: top;
}
th { background: var(--soft); }
.mystery-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 14px;
}
.mystery-card {
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 16px;
    background: #fbfcfe;
}
.archive-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
}
.archive-meta div {
    background: var(--soft);
    border-radius: 12px;
    padding: 10px;
}
.archive-meta dt { color: var(--muted); font-size: 0.78rem; }
.archive-meta dd { margin: 0; font-weight: 700; }
@media print {
    body { background: #fff; }
    .export-page { width: auto; padding: 0; }
    .hero,
    .section-card,
    .story-turn { box-shadow: none; break-inside: avoid; }
    details { break-inside: avoid; }
}
CSS;
    }
}
