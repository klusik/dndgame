<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

/**
 * Authentication state and login logic.
 */
final class Auth
{
    /** @var array<string, mixed>|null */
    private ?array $cachedUser = null;

    /**
     * Store dependencies for authentication operations.
     */
    public function __construct(private Session $session, private UserRepository $users)
    {
    }

    /**
     * Attempt to log in by email and password.
     */
    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if (!$user || (int) $user['is_active'] !== 1) {
            $this->users->recordLoginAttempt($email, false);
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $this->users->recordLoginAttempt($email, false);
            return false;
        }

        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->cachedUser = $user;
        $this->users->recordLoginAttempt($email, true);
        $this->users->updateLastLogin((int) $user['id']);

        return true;
    }

    /**
     * Return true when a user is authenticated.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Return the authenticated user or null.
     *
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $id = $this->session->get('user_id');
        if (!is_int($id)) {
            return null;
        }

        $this->cachedUser = $this->users->findById($id);
        return $this->cachedUser;
    }

    /**
     * Log out the current user.
     */
    public function logout(): void
    {
        $this->session->forget('user_id');
        $this->cachedUser = null;
        $this->session->regenerate();
    }
}
