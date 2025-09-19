<?php
// src/Nexacore/Auth/Authenticatable.php

namespace Nexacore\Auth;

/**
 * Authenticatable Interface
 *
 * Defines the contract for authenticatable user models.
 *
 * @package Nexacore\Auth
 */
interface Authenticatable
{
    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier();

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword();

    /**
     * Get the remember token for the user.
     *
     * @return string|null
     */
    public function getRememberToken();

    /**
     * Set the remember token for the user.
     *
     * @param string $token
     * @return void
     */
    public function setRememberToken($token);

    /**
     * Get the column name for the remember token.
     *
     * @return string
     */
    public function getRememberTokenName();

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName();
}