<?php
// src/Nexacore/Auth/AuthManager.php

namespace Nexacore\Auth;

use DI\Container;
use Nexacore\Auth\Authenticatable;
use Nexacore\Auth\Providers\DatabaseUserProvider;

/**
 * Auth Manager
 *
 * Manages authentication guards and user providers.
 *
 * @package Nexacore\Auth
 */
class AuthManager
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The authentication configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * The active guard instances.
     *
     * @var array
     */
    protected $guards = [];

    /**
     * The user providers.
     *
     * @var array
     */
    protected $providers = [];

    /**
     * Create a new Auth manager instance.
     *
     * @param Container $container
     * @param array $config
     */
    public function __construct(Container $container, array $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * Get a guard instance.
     *
     * @param string|null $name
     * @return \Nexacore\Auth\Guards\SessionGuard
     */
    public function guard(?string $name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->guards[$name] ?? $this->guards[$name] = $this->resolve($name);
    }

    /**
     * Resolve the given guard.
     *
     * @param string $name
     * @return \Nexacore\Auth\Guards\SessionGuard
     */
    protected function resolve(string $name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($name, $config);
        }

        throw new \InvalidArgumentException("Auth driver [{$config['driver']}] is not supported.");
    }

    /**
     * Create a session based authentication guard.
     *
     * @param string $name
     * @param array $config
     * @return \Nexacore\Auth\Guards\SessionGuard
     */
    protected function createSessionDriver(string $name, array $config)
    {
        $provider = $this->createUserProvider($config['provider']);
        
        return new \Nexacore\Auth\Guards\SessionGuard(
            $name,
            $provider,
            $this->container->get('session'),
            $this->container->get('request')
        );
    }

    /**
     * Create a user provider implementation.
     *
     * @param string|null $provider
     * @return UserProvider
     */
    public function createUserProvider(?string $provider = null)
    {
        if (is_null($config = $this->getProviderConfiguration($provider))) {
            throw new \InvalidArgumentException("Authentication user provider [{$provider}] is not defined.");
        }

        $driver = $config['driver'] ?? null;

        if ($driver === 'database') {
            return new DatabaseUserProvider($config['model'], $config['table']);
        }

        throw new \InvalidArgumentException("Authentication user provider [{$driver}] is not supported.");
    }

    /**
     * Get the default authentication driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->config['defaults']['guard'];
    }

    /**
     * Get the user provider configuration.
     *
     * @param string|null $provider
     * @return array|null
     */
    protected function getProviderConfiguration(?string $provider)
    {
        if ($provider = $provider ?: $this->getDefaultUserProvider()) {
            return $this->config['providers'][$provider] ?? null;
        }

        return null;
    }

    /**
     * Get the default user provider name.
     *
     * @return string
     */
    protected function getDefaultUserProvider()
    {
        return $this->config['defaults']['provider'];
    }

    /**
     * Get the guard configuration.
     *
     * @param string $name
     * @return array|null
     */
    protected function getConfig(string $name)
    {
        return $this->config['guards'][$name] ?? null;
    }

    /**
     * Dynamically call the default guard instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->guard()->{$method}(...$parameters);
    }
}