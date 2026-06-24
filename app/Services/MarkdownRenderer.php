<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Renders a restricted Markdown subset used by campaign narratives and notes.
 *
 * The renderer intentionally supports only safe, predictable formatting. It is
 * not a full Markdown implementation and does not allow raw HTML. This keeps
 * pasted model output readable without introducing third-party dependencies.
 */
final class MarkdownRenderer
{
    /**
     * Render a restricted Markdown subset to safe HTML.
     */
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/', str_replace("\r\n", "\n", $markdown)) ?: [];
        $html = [];
        $paragraph = [];
        $listType = null;
        $listItems = [];
        $quoteLines = [];
        $inCode = false;
        $code = [];
        $codeLanguage = '';

        foreach ($lines as $line) {
            if (preg_match('/^```\s*([A-Za-z0-9_-]+)?\s*$/', trim($line), $matches)) {
                $this->flushParagraph($html, $paragraph);
                $this->flushList($html, $listType, $listItems);
                $this->flushQuote($html, $quoteLines);

                if ($inCode) {
                    $html[] = $this->renderCodeBlock($code, $codeLanguage);
                    $code = [];
                    $codeLanguage = '';
                    $inCode = false;
                } else {
                    $inCode = true;
                    $codeLanguage = $matches[1] ?? '';
                }
                continue;
            }

            if ($inCode) {
                $code[] = $line;
                continue;
            }

            $trimmed = trim($line);

            if ($trimmed === '') {
                $this->flushParagraph($html, $paragraph);
                $this->flushList($html, $listType, $listItems);
                $this->flushQuote($html, $quoteLines);
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $matches)) {
                $this->flushParagraph($html, $paragraph);
                $this->flushList($html, $listType, $listItems);
                $this->flushQuote($html, $quoteLines);

                $level = strlen($matches[1]);
                $html[] = '<h' . $level . '>' . $this->inline($matches[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line, $matches)) {
                $this->flushParagraph($html, $paragraph);
                $this->flushList($html, $listType, $listItems);
                $quoteLines[] = rtrim($matches[1]);
                continue;
            }

            if (preg_match('/^[-*+]\s+(.+)$/', $trimmed, $matches)) {
                $this->flushParagraph($html, $paragraph);
                $this->flushQuote($html, $quoteLines);
                if ($listType !== null && $listType !== 'ul') {
                    $this->flushList($html, $listType, $listItems);
                }
                $this->appendListItem($listType, $listItems, 'ul', $matches[1]);
                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.+)$/', $trimmed, $matches)) {
                $this->flushParagraph($html, $paragraph);
                $this->flushQuote($html, $quoteLines);
                if ($listType !== null && $listType !== 'ol') {
                    $this->flushList($html, $listType, $listItems);
                }
                $this->appendListItem($listType, $listItems, 'ol', $matches[1]);
                continue;
            }

            $this->flushList($html, $listType, $listItems);
            $this->flushQuote($html, $quoteLines);
            $paragraph[] = $trimmed;
        }

        if ($inCode) {
            $html[] = $this->renderCodeBlock($code, $codeLanguage);
        }

        $this->flushParagraph($html, $paragraph);
        $this->flushList($html, $listType, $listItems);
        $this->flushQuote($html, $quoteLines);

        return implode("\n", $html);
    }

    /**
     * Append a list item while keeping list type boundaries intact.
     *
     * @param string|null $listType Current list type, if any.
     * @param array<int, string> $listItems Current list item source text.
     */
    private function appendListItem(?string &$listType, array &$listItems, string $newType, string $item): void
    {
        $listType = $newType;
        $listItems[] = $item;
    }

    /**
     * Flush a normal paragraph into the output HTML array.
     *
     * @param array<int, string> $html Output HTML fragments.
     * @param array<int, string> $paragraph Current paragraph lines.
     */
    private function flushParagraph(array &$html, array &$paragraph): void
    {
        if ($paragraph === []) {
            return;
        }

        $text = implode(' ', $paragraph);
        $html[] = '<p>' . $this->inline($text) . '</p>';
        $paragraph = [];
    }

    /**
     * Flush the current list into the output HTML array.
     *
     * @param array<int, string> $html Output HTML fragments.
     * @param string|null $listType Current list type.
     * @param array<int, string> $listItems Current list item source text.
     */
    private function flushList(array &$html, ?string &$listType, array &$listItems): void
    {
        if ($listType === null || $listItems === []) {
            return;
        }

        $tag = $listType === 'ol' ? 'ol' : 'ul';
        $items = [];
        foreach ($listItems as $item) {
            $items[] = '<li>' . $this->inline($item) . '</li>';
        }

        if ($items !== []) {
            $html[] = '<' . $tag . '>' . implode('', $items) . '</' . $tag . '>';
        }

        $listType = null;
        $listItems = [];
    }

    /**
     * Flush quoted lines as either a readable terminal readout or blockquote.
     *
     * @param array<int, string> $html Output HTML fragments.
     * @param array<int, string> $quoteLines Current quote source lines.
     */
    private function flushQuote(array &$html, array &$quoteLines): void
    {
        if ($quoteLines === []) {
            return;
        }

        $lines = $this->trimEmptyEdges($quoteLines);
        if ($lines === []) {
            $quoteLines = [];
            return;
        }

        if ($this->looksLikeReadout($lines)) {
            $html[] = $this->renderReadout($lines);
        } else {
            $paragraphs = $this->splitIntoParagraphs($lines);
            $parts = [];
            foreach ($paragraphs as $paragraph) {
                $parts[] = '<p>' . $this->inline(implode(' ', $paragraph)) . '</p>';
            }
            $html[] = '<blockquote>' . implode('', $parts) . '</blockquote>';
        }

        $quoteLines = [];
    }

    /**
     * Render a fenced code block.
     *
     * @param array<int, string> $code Source code lines.
     */
    private function renderCodeBlock(array $code, string $language): string
    {
        $class = $language !== '' ? ' class="language-' . e($language) . '"' : '';
        return '<pre><code' . $class . '>' . e(implode("\n", $code)) . '</code></pre>';
    }

    /**
     * Decide whether quoted lines are a device or terminal readout.
     *
     * @param array<int, string> $lines Quote lines without leading markers.
     */
    private function looksLikeReadout(array $lines): bool
    {
        $nonEmpty = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
        if (count($nonEmpty) < 2) {
            return false;
        }

        $colonLines = 0;
        foreach ($nonEmpty as $line) {
            if (str_contains($line, ':')) {
                $colonLines++;
            }
        }

        $firstLine = $nonEmpty[0];
        $headingLike = (bool) preg_match('/^[A-Z0-9 _\/-]{4,}$/', $firstLine);

        return $headingLike || $colonLines >= 2;
    }

    /**
     * Render a terminal-like readout as a structured panel instead of raw arrows.
     *
     * @param array<int, string> $lines Readout source lines.
     */
    private function renderReadout(array $lines): string
    {
        $lines = $this->trimEmptyEdges($lines);
        $title = trim((string) ($lines[0] ?? 'Readout'));
        $bodyLines = array_slice($lines, 1);
        $items = [];
        $looseLines = [];

        foreach ($bodyLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^([^:]{1,80}):\s*(.+)$/', $line, $matches)) {
                $items[] = '<li><strong>' . $this->inline($matches[1]) . ':</strong> ' . $this->inline($matches[2]) . '</li>';
            } else {
                $looseLines[] = '<p>' . $this->inline($line) . '</p>';
            }
        }

        $content = '';
        if ($looseLines !== []) {
            $content .= implode('', $looseLines);
        }
        if ($items !== []) {
            $content .= '<ul>' . implode('', $items) . '</ul>';
        }

        return '<section class="readout-panel"><div class="readout-title">' . $this->inline($title) . '</div>' . $content . '</section>';
    }

    /**
     * Remove empty lines from the beginning and end of an array.
     *
     * @param array<int, string> $lines Source lines.
     * @return array<int, string>
     */
    private function trimEmptyEdges(array $lines): array
    {
        while ($lines !== [] && trim((string) $lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim((string) $lines[array_key_last($lines)]) === '') {
            array_pop($lines);
        }
        return array_values($lines);
    }

    /**
     * Split lines into paragraph groups at empty lines.
     *
     * @param array<int, string> $lines Source lines.
     * @return array<int, array<int, string>>
     */
    private function splitIntoParagraphs(array $lines): array
    {
        $paragraphs = [];
        $current = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                if ($current !== []) {
                    $paragraphs[] = $current;
                    $current = [];
                }
                continue;
            }
            $current[] = trim($line);
        }
        if ($current !== []) {
            $paragraphs[] = $current;
        }
        return $paragraphs;
    }

    /**
     * Render safe inline formatting.
     */
    private function inline(string $text): string
    {
        $escaped = e($text);

        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!_)_([^_]+)_(?!_)/', '<em>$1</em>', $escaped) ?? $escaped;

        return $escaped;
    }
}
