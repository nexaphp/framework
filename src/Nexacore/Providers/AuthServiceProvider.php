<?php
// src/Nexacore/Providers/AuthServiceProvider.php

namespace Nexacore\Providers;

use DI\Container;
use Nexacore\Contracts\Providers\ServiceProvider;
use Nexacore\Auth\AuthManager;

/**
 * Auth Service Provider
 *
 * Registers authentication services with the container.
 *
 * @package Nexacore\Providers
 */
class AuthServiceProvider implements ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        $container->set('auth', function () use ($container) {
            $config = $container->get('config')['auth'] ?? [];
            return new AuthManager($container, $config);
        });

        $container->set('auth.driver', function () use ($container) {
            return $container->get('auth')->guard();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // Add auth global to views
        if ($container->has('view')) {
            $view = $container->get('view');
            $auth = $container->get('auth.driver');
            
            $view->getEnvironment()->addGlobal('auth', [
                'check' => $auth->check(),
                'user' => $auth->user(),
            ]);
        }
    }
}