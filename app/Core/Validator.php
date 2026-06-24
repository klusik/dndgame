<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight input validation helper.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * Require a non-empty field.
     */
    public function required(string $field, mixed $value, string $message): self
    {
        if (trim((string) $value) === '') {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * Require a valid email address.
     */
    public function email(string $field, mixed $value, string $message): self
    {
        if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * Require a minimum string length.
     */
    public function min(string $field, mixed $value, int $length, string $message): self
    {
        if (mb_strlen((string) $value) < $length) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * Return true when no validation errors exist.
     */
    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * Return validation errors.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
