<?php
// src/Nexacore/Console/Kernel.php

namespace Nexacore\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Nexacore\Foundation\Application as NexaApplication;
use Cron\CronExpression;

/**
 * Console Kernel for NexaPHP
 *
 * Handles console command registration, scheduling, and execution.
 *
 * @package Nexacore\Console
 */
class Kernel
{
    /**
     * The application instance.
     *
     * @var NexaApplication
     */
    protected $app;

    /**
     * The Symfony Console application.
     *
     * @var Application
     */
    protected $console;

    /**
     * The scheduled commands.
     *
     * @var array
     */
    protected $schedule = [];

    /**
     * Create a new Console Kernel instance.
     *
     * @param NexaApplication $app
     */
    public function __construct(NexaApplication $app)
    {
        $this->app = $app;
        $this->console = new Application('NexaPHP Console', $app->version());
        $this->console->setAutoExit(false);
    }

    /**
     * Handle an incoming console command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap();
        
        try {
            $status = $this->console->run($input, $output);
        } catch (\Exception $e) {
            $this->reportException($e, $output);
            $status = 1;
        }
        
        $this->terminate();
        
        return $status;
    }

    /**
     * Bootstrap the application for console commands.
     *
     * @return void
     */
    protected function bootstrap(): void
    {
        // Set the application environment for console
        if (!$this->app->environment()) {
            $this->app->detectEnvironment(function () {
                return 'production';
            });
        }

        // Register console service providers
        $this->app->register(\Nexacore\Providers\ConsoleServiceProvider::class);

        // Load console commands
        $this->loadCommands();

        // Load scheduled commands
        $this->loadSchedule();
    }

    /**
     * Load the console commands.
     *
     * @return void
     */
    protected function loadCommands(): void
    {
        $commands = $this->getCommands();
        
        foreach ($commands as $command) {
            if (class_exists($command)) {
                $this->console->add($this->app->getContainer()->get($command));
            }
        }
    }

    /**
     * Get the application commands.
     *
     * @return array
     */
    protected function getCommands(): array
    {
        $config = $this->app->get('config');
        
        return array_merge(
            $config['console']['commands'] ?? [],
            $this->getFrameworkCommands()
        );
    }

    /**
     * Get the framework default commands.
     *
     * @return array
     */
    protected function getFrameworkCommands(): array
    {
        return [
            \Nexacore\Console\Commands\ServeCommand::class,
            \Nexacore\Console\Commands\RouteListCommand::class,
            \Nexacore\Console\Commands\RouteCacheCommand::class,
            \Nexacore\Console\Commands\MakeControllerCommand::class,
            \Nexacore\Console\Commands\MakeModelCommand::class,
            \Nexacore\Console\Commands\ScheduleRunCommand::class,
            \Nexacore\Console\Commands\KeyGenerateCommand::class,
            \Nexacore\Console\Commands\StorageLinkCommand::class,
        ];
    }

    /**
     * Load the scheduled commands.
     *
     * @return void
     */
    protected function loadSchedule(): void
    {
        if (method_exists($this, 'schedule')) {
            $this->app->call([$this, 'schedule']);
        }
    }

    /**
     * Schedule a console command.
     *
     * @param string $command
     * @param array $parameters
     * @return \Nexacore\Console\Scheduling\Event
     */
    public function command(string $command, array $parameters = []): Scheduling\Event
    {
        $event = new Scheduling\Event($command, $parameters);
        $this->schedule[] = $event;
        
        return $event;
    }

    /**
     * Schedule a callable.
     *
     * @param callable $callback
     * @param array $parameters
     * @return \Nexacore\Console\Scheduling\Event
     */
    public function call(callable $callback, array $parameters = []): Scheduling\Event
    {
        $event = new Scheduling\CallbackEvent($callback, $parameters);
        $this->schedule[] = $event;
        
        return $event;
    }

    /**
     * Get the scheduled events.
     *
     * @return array
     */
    public function getSchedule(): array
    {
        return $this->schedule;
    }

    /**
     * Run the scheduled commands.
     *
     * @return void
     */
    public function runScheduledCommands(): void
    {
        foreach ($this->schedule as $event) {
            if ($event->isDue()) {
                $event->run();
            }
        }
    }

    /**
     * Report an exception to the console.
     *
     * @param \Exception $e
     * @param OutputInterface $output
     * @return void
     */
    protected function reportException(\Exception $e, OutputInterface $output): void
    {
        $output->writeln('<error>' . $e->getMessage() . '</error>');
        
        if ($this->app->get('config')['app']['debug'] ?? false) {
            $output->writeln('<comment>' . $e->getTraceAsString() . '</comment>');
        }
    }

    /**
     * Terminate the console application.
     *
     * @return void
     */
    protected function terminate(): void
    {
        $this->app->terminate();
    }

    /**
     * Get the Symfony Console application.
     *
     * @return Application
     */
    public function getConsole(): Application
    {
        return $this->console;
    }
}