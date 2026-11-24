<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Small safe Markdown renderer for internal campaign notes.
 */
final class MarkdownRenderer
{
    /**
     * Render a restricted Markdown subset to HTML.
     */
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $html = [];
        $inList = false;
        $inCode = false;
        $code = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '```')) {
                if ($inCode) {
                    $html[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
                    $code = [];
                    $inCode = false;
                } else {
                    if ($inList) {
                        $html[] = '</ul>';
                        $inList = false;
                    }
                    $inCode = true;
                }
                continue;
            }

            if ($inCode) {
                $code[] = $line;
                continue;
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $matches)) {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $level = strlen($matches[1]);
                $html[] = '<h' . $level . '>' . $this->inline($matches[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches)) {
                if (!$inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>' . $this->inline($matches[1]) . '</li>';
                continue;
            }

            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $html[] = '<p>' . $this->inline($trimmed) . '</p>';
        }

        if ($inCode) {
            $html[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
        }
        if ($inList) {
            $html[] = '</ul>';
        }

        return implode("\n", $html);
    }

    /**
     * Render safe inline formatting.
     */
    private function inline(string $text): string
    {
        $escaped = e($text);
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        return $escaped;
    }
}
