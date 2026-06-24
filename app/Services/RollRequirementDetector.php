<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Detects whether the latest DM narrative is waiting for a player dice roll.
 *
 * This is intentionally heuristic because model output is plain Markdown. The
 * detector is conservative enough for RPG workflow purposes and supports the
 * standardized roll request format emitted by the prompt compiler.
 */
final class RollRequirementDetector
{
    /**
     * Inspect narrative text and return the roll requirement state.
     *
     * @return array{required:bool, expression:string, reason:string, summary:string}
     */
    public function detect(string $narrative): array
    {
        $text = trim($narrative);
        if ($text === '') {
            return $this->emptyResult();
        }

        $expression = $this->extractExpression($text) ?? '1d20';
        $reason = $this->extractReason($text);
        $required = $this->containsRollRequest($text);

        if (!$required) {
            return $this->emptyResult();
        }

        return [
            'required' => true,
            'expression' => $expression,
            'reason' => $reason,
            'summary' => sprintf('%s is required before generating the next prompt.', $reason),
        ];
    }

    /**
     * Return an empty detector result.
     *
     * @return array{required:bool, expression:string, reason:string, summary:string}
     */
    private function emptyResult(): array
    {
        return [
            'required' => false,
            'expression' => '1d20',
            'reason' => 'Requested check',
            'summary' => '',
        ];
    }

    /**
     * Decide whether the narrative is asking for a roll now.
     */
    private function containsRollRequest(string $text): bool
    {
        $normalized = strtolower($text);
        $patterns = [
            '/\broll\s*:\s*\*?\*?\s*\d*d\d+/i',
            '/\broll\s+\d*d\d+/i',
            '/\broll\s+1d20\b/i',
            '/\broll\s+and\s+tell\s+me\b/i',
            '/\bcheck\s+required\b/i',
            '/\brequired\s+check\b/i',
            '/\bskill\s+check\s+required\b/i',
            '/\brecall\s+check\s+required\b/i',
            '/\bplease\s+roll\b/i',
            '/\bmake\s+(?:a|an)\s+.+?\s+check\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the first dice expression from a roll request.
     */
    private function extractExpression(string $text): ?string
    {
        if (preg_match('/\b(\d*d\d+(?:kh1|kl1)?(?:\s*[+-]\s*\d+)?)\b/i', $text, $match) !== 1) {
            return null;
        }

        $expression = strtolower(str_replace(' ', '', $match[1]));
        return str_starts_with($expression, 'd') ? '1' . $expression : $expression;
    }

    /**
     * Extract a compact reason for the roll display.
     */
    private function extractReason(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $line) {
            $clean = trim(strip_tags($line));
            $clean = trim($clean, " \t\n\r\0\x0B#*:.");
            if ($clean === '') {
                continue;
            }

            if (preg_match('/^(.{3,80}?check)\s+required$/i', $clean, $match) === 1) {
                return $this->cleanReason($match[1]);
            }

            if (preg_match('/^(.{3,80}?check)$/i', $clean, $match) === 1) {
                return $this->cleanReason($match[1]);
            }
        }

        if (preg_match('/\b([A-Z][A-Za-z ]{2,60}? Check)\s+required\b/', $text, $match) === 1) {
            return $this->cleanReason($match[1]);
        }

        return 'Requested check';
    }

    /**
     * Normalize a detected roll reason for display and prompt insertion.
     */
    private function cleanReason(string $reason): string
    {
        $reason = preg_replace('/\s+/', ' ', trim($reason)) ?: 'Requested check';
        $reason = preg_replace('/\s+required$/i', '', $reason) ?: $reason;
        return function_exists('mb_substr') ? mb_substr($reason, 0, 80) : substr($reason, 0, 80);
    }
}
