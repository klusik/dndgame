<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Extracts reader-visible player input from generated prompt text.
 */
final class PlayerInputExtractor
{
    /**
     * Extract the player action and dice result sections from a generated prompt.
     *
     * @return array{action:string,dice:string}
     */
    public function extractFromPrompt(string $prompt): array
    {
        return [
            'action' => $this->extractSection(
                $prompt,
                'Current player action or request:',
                ['Dice results already rolled by the player for this turn:', '[BEGIN_DICE_RESULTS]', 'Additional operator notes:']
            ),
            'dice' => $this->extractBracketedSection($prompt, 'BEGIN_DICE_RESULTS', 'END_DICE_RESULTS'),
        ];
    }

    /**
     * Extract a free-text section from generated prompt text.
     *
     * @param array<int, string> $endMarkers
     */
    private function extractSection(string $text, string $startMarker, array $endMarkers): string
    {
        $start = strpos($text, $startMarker);
        if ($start === false) {
            return '';
        }

        $start += strlen($startMarker);
        $end = strlen($text);
        foreach ($endMarkers as $marker) {
            $markerPosition = strpos($text, $marker, $start);
            if ($markerPosition !== false && $markerPosition < $end) {
                $end = $markerPosition;
            }
        }

        return trim(substr($text, $start, $end - $start));
    }

    /**
     * Extract a bracketed section from generated prompt text.
     */
    private function extractBracketedSection(string $text, string $beginMarker, string $endMarker): string
    {
        $pattern = '/\[' . preg_quote($beginMarker, '/') . '\](.*?)\[' . preg_quote($endMarker, '/') . '\]/s';
        if (!preg_match($pattern, $text, $matches)) {
            return '';
        }

        return trim((string) $matches[1]);
    }
}
