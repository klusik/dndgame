<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Extracts narrative text and JSON state delta from a pasted ChatGPT response.
 */
final class ChatResponseParser
{
    /**
     * Parse a pasted response.
     *
     * @return array{ok:bool,narrative:string,delta:array<string,mixed>|null,delta_raw:string,errors:array<int,string>}
     */
    public function parse(string $response): array
    {
        $narrative = $this->between($response, '[BEGIN_NARRATIVE]', '[END_NARRATIVE]');
        $deltaRaw = $this->between($response, '[BEGIN_STATE_DELTA_JSON]', '[END_STATE_DELTA_JSON]');
        $errors = [];

        if ($narrative === '') {
            $errors[] = 'Narrative markers were not found.';
            $narrative = trim($response);
        }

        if ($deltaRaw === '') {
            $errors[] = 'State delta JSON markers were not found.';
            return [
                'ok' => false,
                'narrative' => $narrative,
                'delta' => null,
                'delta_raw' => '',
                'errors' => $errors,
            ];
        }

        $decoded = json_decode($deltaRaw, true);
        if (!is_array($decoded)) {
            $errors[] = 'State delta JSON is invalid: ' . json_last_error_msg();
            return [
                'ok' => false,
                'narrative' => $narrative,
                'delta' => null,
                'delta_raw' => $deltaRaw,
                'errors' => $errors,
            ];
        }

        return [
            'ok' => $errors === [],
            'narrative' => $narrative,
            'delta' => $decoded,
            'delta_raw' => $deltaRaw,
            'errors' => $errors,
        ];
    }

    /**
     * Extract text between two markers.
     */
    private function between(string $text, string $start, string $end): string
    {
        $startPosition = mb_strpos($text, $start);
        if ($startPosition === false) {
            return '';
        }
        $startPosition += mb_strlen($start);
        $endPosition = mb_strpos($text, $end, $startPosition);
        if ($endPosition === false) {
            return '';
        }

        return trim(mb_substr($text, $startPosition, $endPosition - $startPosition));
    }
}
