<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Repository for users and login attempts.
 */
final class UserRepository extends BaseRepository
{
    /**
     * Find a user by primary key.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Find a user by email.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Create the first administrator account.
     */
    public function createAdmin(string $email, string $displayName, string $passwordHash, string $locale): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (email, password_hash, display_name, role, locale, is_active, created_at, updated_at)
             VALUES (:email, :password_hash, :display_name, :role, :locale, 1, :created_at, :updated_at)'
        );
        $now = $this->now();
        $stmt->execute([
            'email' => mb_strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
            'role' => 'admin',
            'locale' => $locale,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Update the last login timestamp.
     */
    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo()->prepare('UPDATE users SET last_login_at = :last_login_at WHERE id = :id');
        $stmt->execute(['last_login_at' => $this->now(), 'id' => $userId]);
    }

    /**
     * Record a login attempt for auditing and simple troubleshooting.
     */
    public function recordLoginAttempt(string $email, bool $success): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO login_attempts (email, ip_address, was_successful, attempted_at)
             VALUES (:email, :ip_address, :was_successful, :attempted_at)'
        );
        $stmt->execute([
            'email' => mb_strtolower(trim($email)),
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'was_successful' => $success ? 1 : 0,
            'attempted_at' => $this->now(),
        ]);
    }

    /**
     * Count all users.
     */
    public function countAll(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
