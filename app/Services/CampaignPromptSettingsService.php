<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingRepository;

/**
 * Stores campaign-specific prompt generation defaults in the existing settings table.
 *
 * The application intentionally avoids adding a new database migration for this small
 * preference bundle. Values are stored as JSON under a campaign-scoped settings key.
 */
final class CampaignPromptSettingsService
{
    /**
     * Store the settings repository dependency.
     */
    public function __construct(private SettingRepository $settings)
    {
    }

    /**
     * Return prompt defaults for a campaign.
     *
     * @return array<string, string>
     */
    public function getForCampaign(int $campaignId, string $fallbackLanguage = 'en'): array
    {
        $defaults = $this->defaults($fallbackLanguage);
        $raw = $this->settings->get($this->settingsKey($campaignId), '');
        if (!is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return $this->normalize($decoded + $defaults, $fallbackLanguage);
    }

    /**
     * Build normalized settings from submitted form values.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function fromInput(array $input, string $fallbackLanguage = 'en'): array
    {
        return $this->normalize($input, $fallbackLanguage);
    }

    /**
     * Persist prompt defaults for a campaign.
     *
     * @param array<string, string> $settings
     */
    public function saveForCampaign(int $campaignId, array $settings, string $fallbackLanguage = 'en'): void
    {
        $normalized = $this->normalize($settings, $fallbackLanguage);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Failed to encode prompt settings.');
        }

        $this->settings->set($this->settingsKey($campaignId), $json);
    }

    /**
     * Return application defaults for prompt generation.
     *
     * @return array<string, string>
     */
    public function defaults(string $fallbackLanguage = 'en'): array
    {
        return [
            'response_language' => $this->normalizeLanguage($fallbackLanguage),
            'narrative_length' => 'medium',
            'challenge_level' => 'balanced',
            'dice_policy' => 'occasional',
            'agency_mode' => 'player_led',
            'description_style' => 'direct',
            'dm_stance' => 'guarded',
        ];
    }

    /**
     * Normalize all supported prompt settings.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function normalize(array $input, string $fallbackLanguage): array
    {
        $defaults = $this->defaults($fallbackLanguage);

        return [
            'response_language' => $this->normalizeLanguage((string) ($input['response_language'] ?? $defaults['response_language'])),
            'narrative_length' => $this->normalizeOption((string) ($input['narrative_length'] ?? $defaults['narrative_length']), ['compact', 'medium', 'rich'], 'medium'),
            'challenge_level' => $this->normalizeOption((string) ($input['challenge_level'] ?? $defaults['challenge_level']), ['gentle', 'balanced', 'demanding'], 'balanced'),
            'dice_policy' => $this->normalizeOption((string) ($input['dice_policy'] ?? $defaults['dice_policy']), ['rare', 'occasional', 'frequent'], 'occasional'),
            'agency_mode' => $this->normalizeOption((string) ($input['agency_mode'] ?? $defaults['agency_mode']), ['guided', 'player_led', 'sandbox'], 'player_led'),
            'description_style' => $this->normalizeOption((string) ($input['description_style'] ?? $defaults['description_style']), ['direct', 'plain', 'atmospheric'], 'direct'),
            'dm_stance' => $this->normalizeOption((string) ($input['dm_stance'] ?? $defaults['dm_stance']), ['straight', 'guarded', 'shady'], 'guarded'),
        ];
    }

    /**
     * Normalize one enum-like option.
     *
     * @param array<int, string> $allowed
     */
    private function normalizeOption(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Normalize supported response language code.
     */
    private function normalizeLanguage(string $language): string
    {
        return $language === 'cs' ? 'cs' : 'en';
    }

    /**
     * Return a campaign-scoped settings key.
     */
    private function settingsKey(int $campaignId): string
    {
        return 'campaign.' . $campaignId . '.prompt_settings';
    }
}
