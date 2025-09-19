<?php
// src/Nexacore/Auth/Providers/DatabaseUserProvider.php

namespace Nexacore\Auth\Providers;

use Nexacore\Auth\UserProvider;
use Nexacore\Auth\Authenticatable;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Database User Provider
 *
 * Retrieves users from a database table.
 *
 * @package Nexacore\Auth\Providers
 */
class DatabaseUserProvider implements UserProvider
{
    /**
     * The user model class.
     *
     * @var string
     */
    protected $model;

    /**
     * The database table.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database user provider.
     *
     * @param string $model
     * @param string $table
     */
    public function __construct(string $model, string $table)
    {
        $this->model = $model;
        $this->table = $table;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $user = DB::table($this->table)
            ->where($this->getAuthIdentifierName(), $identifier)
            ->first();

        return $user ? $this->createModel($user) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = DB::table($this->table)
            ->where($this->getAuthIdentifierName(), $identifier)
            ->where($this->getRememberTokenName(), $token)
            ->first();

        return $user ? $this->createModel($user) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        DB::table($this->table)
            ->where($this->getAuthIdentifierName(), $user->getAuthIdentifier())
            ->update([$this->getRememberTokenName() => $token]);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials)) {
            return null;
        }

        $query = DB::table($this->table);

        foreach ($credentials as $key => $value) {
            if (!str_contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        $user = $query->first();

        return $user ? $this->createModel($user) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return password_verify($credentials['password'], $user->getAuthPassword());
    }

    /**
     * Create a new instance of the model.
     *
     * @param object $user
     * @return Authenticatable
     */
    protected function createModel(object $user): Authenticatable
    {
        $class = $this->model;
        return new $class((array) $user);
    }

    /**
     * Get the name of the identifier field.
     *
     * @return string
     */
    protected function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the name of the remember token field.
     *
     * @return string
     */
    protected function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}