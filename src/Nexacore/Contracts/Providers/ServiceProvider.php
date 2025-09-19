<?php
// src/Nexacore/Foundation/Providers/ServiceProvider.php

namespace Nexacore\Contracts\Providers;

use DI\Container;

/**
 * Service Provider Interface for NexaPHP
 *
 * Defines the contract for service providers that register
 * bindings and bootstrapping logic with the application container.
 *
 * @package Nexacore\Foundation\Providers
 */
interface ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method is called when the service provider is registered.
     * Use this to bind services into the container.
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void;

    /**
     * Bootstrap any application services.
     *
     * This method is called after all services have been registered.
     * Use this to perform initialization, event listening, etc.
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void;
}