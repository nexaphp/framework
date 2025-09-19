<?php
// src/Nexacore/Providers/AppServiceProvider.php

namespace Nexacore\Providers;

use DI\Container;
use Nexacore\Contracts\Providers\ServiceProvider;
use Slim\Views\Twig;

/**
 * App Service Provider for NexaPHP
 *
 * Registers core application services like view engine, logging, etc.
 *
 * @package Nexacore\Providers
 */
class AppServiceProvider implements ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Register Twig view engine
        $container->set('view', function() use ($container) {
            $config = $container->get('config');
            return Twig::create(
                $container->get('path.resources') . '/views',
                [
                    'cache' => $config['view']['cache'] ?? false
                        ? $container->get('path.storage') . '/framework/views' 
                        : false,
                    'debug' => $config['view']['debug'] ?? false
                ]
            );
        });

        // Register logger
        $container->set('logger', function() use ($container) {
            // Basic file logger implementation
            $logPath = $container->get('path.storage') . '/logs/nexaphp.log';
            $logger = new \Monolog\Logger('nexaphp');
            $logger->pushHandler(new \Monolog\Handler\StreamHandler($logPath));
            return $logger;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // Add global variables to all views
        if ($container->has('view')) {
            $view = $container->get('view');
            $config = $container->get('config');
            
            $view->getEnvironment()->addGlobal('app_name', $config['app']['name'] ?? 'NexaPHP');
            $view->getEnvironment()->addGlobal('app_env', $config['app']['env'] ?? 'production');
        }

        // Boot other application services
        $this->bootErrorHandling($container);
    }

    /**
     * Bootstrap error handling.
     *
     * @param Container $container
     * @return void
     */
    protected function bootErrorHandling(Container $container): void
    {
        if ($container->has('logger')) {
            set_error_handler(function ($level, $message, $file = '', $line = 0) use ($container) {
                $container->get('logger')->error("Error: {$message} in {$file}:{$line}", [
                    'level' => $level,
                    'file' => $file,
                    'line' => $line
                ]);
            });
        }
    }
}