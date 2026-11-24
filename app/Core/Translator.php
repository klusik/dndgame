<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File-based translation service.
 */
final class Translator
{
    /** @var array<string, string> */
    private array $messages = [];

    /**
     * Load translation messages for a locale.
     */
    public function __construct(private string $langPath, private string $locale)
    {
        $this->load($locale);
    }

    /**
     * Return the current locale code.
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Translate a message key.
     *
     * @param array<string, scalar> $replace
     */
    public function get(string $key, array $replace = []): string
    {
        $message = $this->messages[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $message = str_replace(':' . $name, (string) $value, $message);
        }
        return $message;
    }

    /**
     * Load messages from a language file.
     */
    private function load(string $locale): void
    {
        $file = rtrim($this->langPath, '/\\') . DIRECTORY_SEPARATOR . $locale . '.php';
        if (!is_file($file)) {
            $file = rtrim($this->langPath, '/\\') . DIRECTORY_SEPARATOR . 'en.php';
            $this->locale = 'en';
        }

        $messages = require $file;
        $this->messages = is_array($messages) ? $messages : [];
    }
}
