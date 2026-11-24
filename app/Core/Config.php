<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only configuration accessor with dot notation support.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $values;

    /**
     * Store loaded configuration values.
     *
     * @param array<string, mixed> $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * Get a configuration value by dot-separated key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
