<?php
// src/Nexacore/Foundation/Application.php

namespace Nexacore\Contracts\Foundation;

use Psr\Container\ContainerInterface;
use Slim\App;
use Nexacore\Contracts\Providers\ServiceProvider;

/**
 * Application Interface for NexaPHP
 *
 * Defines the contract for the main application instance.
 * This follows PSR-11 ContainerInterface for dependency injection.
 *
 * @package Nexacore\Foundation
 */
interface Application extends ContainerInterface
{
    /**
     * Get the version of the NexaPHP framework.
     *
     * @return string
     */
    public function version(): string;

    /**
     * Get the base path of the application.
     *
     * @param string $path
     * @return string
     */
    public function basePath(string $path = ''): string;

    /**
     * Get the path to the application configuration files.
     *
     * @param string $path
     * @return string
     */
    public function configPath(string $path = ''): string;

    /**
     * Get the path to the database directory.
     *
     * @param string $path
     * @return string
     */
    public function databasePath(string $path = ''): string;

    /**
     * Get the path to the public directory.
     *
     * @param string $path
     * @return string
     */
    public function publicPath(string $path = ''): string;

    /**
     * Get the path to the storage directory.
     *
     * @param string $path
     * @return string
     */
    public function storagePath(string $path = ''): string;

    /**
     * Get the path to the resources directory.
     *
     * @param string $path
     * @return string
     */
    public function resourcePath(string $path = ''): string;

    /**
     * Get the path to the routes directory.
     *
     * @param string $path
     * @return string
     */
    public function routesPath(string $path = ''): string;

    /**
     * Get the application environment.
     *
     * @return string
     */
    public function environment(): string;

    /**
     * Determine if the application is in production.
     *
     * @return bool
     */
    public function isProduction(): bool;

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool;

    /**
     * Register a service provider with the application.
     *
     * @param string|ServiceProvider $provider
     * @param bool $force
     * @return ServiceProvider
     */
    public function register($provider, bool $force = false): ServiceProvider;

    /**
     * Bootstrap the application's service providers.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Get the registered service provider instances.
     *
     * @return ServiceProvider[]
     */
    public function getProviders(): array;

    /**
     * Determine if the application has been bootstrapped.
     *
     * @return bool
     */
    public function isBooted(): bool;

    /**
     * Register all of the configured providers.
     *
     * @return void
     */
    public function registerConfiguredProviders(): void;

    /**
     * Get the Slim application instance.
     *
     * @return App
     */
    public function getSlim(): App;

    /**
     * Get the DI container instance.
     *
     * @return \DI\Container
     */
    public function getContainer(): \DI\Container;

    /**
     * Set the base path for the application.
     *
     * @param string $path
     * @return void
     */
    public function setBasePath(string $path): void;
}