<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for global key-value settings.
 */
final class SettingRepository extends BaseRepository
{
    /**
     * Get a setting by key.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo()->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? $value : $default;
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, string $value): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (:setting_key, :setting_value, :updated_at)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_at' => $this->now(),
        ]);
    }
}
