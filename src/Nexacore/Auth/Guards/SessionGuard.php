<?php
// src/Nexacore/Auth/Guards/SessionGuard.php

namespace Nexacore\Auth\Guards;

use Nexacore\Auth\Authenticatable;
use Nexacore\Auth\UserProvider;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Session Guard
 *
 * Provides session-based authentication.
 *
 * @package Nexacore\Auth\Guards
 */
class SessionGuard
{
    /**
     * The guard name.
     *
     * @var string
     */
    protected $name;

    /**
     * The user provider implementation.
     *
     * @var UserProvider
     */
    protected $provider;

    /**
     * The session store.
     *
     * @var mixed
     */
    protected $session;

    /**
     * The current request.
     *
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * The currently authenticated user.
     *
     * @var Authenticatable|null
     */
    protected $user;

    /**
     * Create a new session guard.
     *
     * @param string $name
     * @param UserProvider $provider
     * @param mixed $session
     * @param ServerRequestInterface $request
     */
    public function __construct(string $name, UserProvider $provider, $session, ServerRequestInterface $request)
    {
        $this->name = $name;
        $this->provider = $provider;
        $this->session = $session;
        $this->request = $request;
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the currently authenticated user.
     *
     * @return Authenticatable|null
     */
    public function user(): ?Authenticatable
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $id = $this->session->get($this->getName());

        if (!is_null($id)) {
            $this->user = $this->provider->retrieveById($id);
        }

        return $this->user;
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|string|null
     */
    public function id()
    {
        if ($this->user()) {
            return $this->user()->getAuthIdentifier();
        }

        return null;
    }

    /**
     * Validate a user's credentials.
     *
     * @param array $credentials
     * @return bool
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param array $credentials
     * @param bool $remember
     * @return bool
     */
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * Log a user into the application.
     *
     * @param Authenticatable $user
     * @param bool $remember
     * @return void
     */
    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        if ($remember) {
            $this->ensureRememberTokenIsSet($user);
            $this->queueRecallerCookie($user);
        }

        $this->user = $user;
    }

    /**
     * Log the user out of the application.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->clearUserDataFromStorage();

        $this->user = null;
    }

    /**
     * Update the session with the given user ID.
     *
     * @param mixed $id
     * @return void
     */
    protected function updateSession($id): void
    {
        $this->session->set($this->getName(), $id);
        $this->session->regenerate();
    }

    /**
     * Get a unique identifier for the auth session value.
     *
     * @return string
     */
    protected function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }

    /**
     * Ensure the remember token is set on the user.
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function ensureRememberTokenIsSet(Authenticatable $user): void
    {
        if (empty($user->getRememberToken())) {
            $this->cycleRememberToken($user);
        }
    }

    /**
     * Cycle the remember token on the user.
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function cycleRememberToken(Authenticatable $user): void
    {
        $token = bin2hex(random_bytes(20));
        $user->setRememberToken($token);
        $this->provider->updateRememberToken($user, $token);
    }

    /**
     * Queue the recaller cookie into the cookie jar.
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function queueRecallerCookie(Authenticatable $user): void
    {
        // Implement cookie queuing if needed
    }

    /**
     * Clear the user data from the session and cookies.
     *
     * @return void
     */
    protected function clearUserDataFromStorage(): void
    {
        $this->session->remove($this->getName());
        $this->session->regenerate();
    }
}