<?php
// src/Nexacore/Providers/ConsoleServiceProvider.php

namespace Nexacore\Providers;

use DI\Container;
use Nexacore\Foundation\Providers\ServiceProvider;
use Nexacore\Console\Kernel;

/**
 * Console Service Provider for NexaPHP
 *
 * Registers console services and commands.
 *
 * @package Nexacore\Providers
 */
class ConsoleServiceProvider implements ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        $container->set('console.kernel', function () use ($container) {
            return new Kernel($container->get('nexa.app'));
        });

        $container->set('console.schedule', function () use ($container) {
            return $container->get('console.kernel')->getSchedule();
        });

        // Register console commands
        $this->registerCommands($container);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // Console-specific boot logic
    }

    /**
     * Register console commands in the container.
     *
     * @param Container $container
     * @return void
     */
    protected function registerCommands(Container $container): void
    {
        $commands = [
            // Framework commands
            \Nexacore\Console\Commands\ServeCommand::class,
            \Nexacore\Console\Commands\RouteListCommand::class,
            \Nexacore\Console\Commands\RouteCacheCommand::class,
            \Nexacore\Console\Commands\MakeControllerCommand::class,
            \Nexacore\Console\Commands\MakeModelCommand::class,
            \Nexacore\Console\Commands\ScheduleRunCommand::class,
            \Nexacore\Console\Commands\KeyGenerateCommand::class,
            \Nexacore\Console\Commands\StorageLinkCommand::class,
            
            // Application commands will be auto-registered from config
        ];

        foreach ($commands as $command) {
            $container->set($command, function () use ($container, $command) {
                return new $command($container);
            });
        }
    }
}