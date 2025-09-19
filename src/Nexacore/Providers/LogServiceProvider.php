<?php
// src/Nexacore/Providers/LogServiceProvider.php

namespace Nexacore\Providers;

use DI\Container;
use Nexacore\Contracts\Providers\ServiceProvider;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\MemoryUsageProcessor;

/**
 * Log Service Provider for NexaPHP
 *
 * Sets up Monolog logging with multiple channels and handlers.
 *
 * @package Nexacore\Providers
 */
class LogServiceProvider implements ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        $container->set('logger', function () use ($container) {
            return $this->createLogger($container);
        });

        $container->set('log.channels', function () use ($container) {
            return $this->createLogChannels($container);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // Register error handler to use Monolog
        $logger = $container->get('logger');
        set_error_handler(function ($level, $message, $file = '', $line = 0) use ($logger) {
            if (error_reporting() & $level) {
                $logger->error("Error: {$message} in {$file}:{$line}", [
                    'level' => $level,
                    'file' => $file,
                    'line' => $line
                ]);
            }
        });

        // Register exception handler
        set_exception_handler(function ($exception) use ($logger) {
            $logger->emergency('Uncaught Exception: ' . $exception->getMessage(), [
                'exception' => $exception,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
            
            // Re-throw for the normal error handling flow
            throw $exception;
        });
    }

    /**
     * Create the main application logger.
     *
     * @param Container $container
     * @return Logger
     */
    protected function createLogger(Container $container): Logger
    {
        $config = $container->get('config');
        $logConfig = $config['logging'] ?? [];
        
        $logger = new Logger('nexaphp');
        
        // Add processors
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        
        // Add handlers based on configuration
        $this->addHandlers($logger, $logConfig, $container);
        
        return $logger;
    }

    /**
     * Create multiple log channels.
     *
     * @param Container $container
     * @return array
     */
    protected function createLogChannels(Container $container): array
    {
        $config = $container->get('config');
        $logConfig = $config['logging'] ?? [];
        $channels = [];
        
        $channelConfigs = $logConfig['channels'] ?? [
            'app' => ['path' => 'logs/app.log', 'level' => Logger::DEBUG],
            'error' => ['path' => 'logs/error.log', 'level' => Logger::ERROR],
            'security' => ['path' => 'logs/security.log', 'level' => Logger::WARNING],
            'database' => ['path' => 'logs/database.log', 'level' => Logger::INFO],
        ];
        
        foreach ($channelConfigs as $name => $channelConfig) {
            $channels[$name] = $this->createChannelLogger($name, $channelConfig, $container);
        }
        
        return $channels;
    }

    /**
     * Create a channel-specific logger.
     *
     * @param string $name
     * @param array $config
     * @param Container $container
     * @return Logger
     */
    protected function createChannelLogger(string $name, array $config, Container $container): Logger
    {
        $logger = new Logger($name);
        
        $logger->pushProcessor(new UidProcessor());
        
        $path = $config['path'] ?? "logs/{$name}.log";
        $level = $config['level'] ?? Logger::DEBUG;
        $maxFiles = $config['max_files'] ?? 7;
        
        $logPath = $container->get('path.storage') . '/' . ltrim($path, '/');
        $this->ensureLogDirectoryExists(dirname($logPath));
        
        $handler = new RotatingFileHandler($logPath, $maxFiles, $level);
        $handler->setFormatter($this->getLogFormatter());
        
        $logger->pushHandler($handler);
        
        return $logger;
    }

    /**
     * Add handlers to the logger based on configuration.
     *
     * @param Logger $logger
     * @param array $config
     * @param Container $container
     * @return void
     */
    protected function addHandlers(Logger $logger, array $config, Container $container): void
    {
        $defaultLevel = $config['level'] ?? Logger::DEBUG;
        $logPath = $container->get('path.storage') . '/logs/nexaphp.log';
        
        // Ensure log directory exists
        $this->ensureLogDirectoryExists(dirname($logPath));
        
        // Main rotating file handler
        $fileHandler = new RotatingFileHandler(
            $logPath,
            $config['max_files'] ?? 7,
            $defaultLevel
        );
        $fileHandler->setFormatter($this->getLogFormatter());
        $logger->pushHandler($fileHandler);
        
        // Error log handler (separate file for errors)
        $errorLogPath = $container->get('path.storage') . '/logs/error.log';
        $errorHandler = new RotatingFileHandler(
            $errorLogPath,
            $config['max_files'] ?? 7,
            Logger::ERROR
        );
        $errorHandler->setFormatter($this->getLogFormatter());
        $logger->pushHandler($errorHandler);
        
        // Console output in development
        if (($config['console'] ?? false) && php_sapi_name() === 'cli') {
            $consoleHandler = new StreamHandler('php://stdout', $defaultLevel);
            $consoleHandler->setFormatter($this->getLogFormatter());
            $logger->pushHandler($consoleHandler);
        }
        
        // Add additional handlers from config
        $this->addAdditionalHandlers($logger, $config, $container);
    }

    /**
     * Add additional handlers from configuration.
     *
     * @param Logger $logger
     * @param array $config
     * @param Container $container
     * @return void
     */
    protected function addAdditionalHandlers(Logger $logger, array $config, Container $container): void
    {
        $handlers = $config['handlers'] ?? [];
        
        foreach ($handlers as $handlerConfig) {
            $handler = $this->createHandlerFromConfig($handlerConfig, $container);
            if ($handler) {
                $logger->pushHandler($handler);
            }
        }
    }

    /**
     * Create a handler from configuration.
     *
     * @param array $config
     * @param Container $container
     * @return \Monolog\Handler\HandlerInterface|null
     */
    protected function createHandlerFromConfig(array $config, Container $container)
    {
        $type = $config['type'] ?? '';
        $level = $config['level'] ?? Logger::DEBUG;
        
        switch ($type) {
            case 'file':
                $path = $container->get('path.storage') . '/' . ltrim($config['path'] ?? 'logs/app.log', '/');
                $this->ensureLogDirectoryExists(dirname($path));
                return new StreamHandler($path, $level);
                
            case 'rotate':
                $path = $container->get('path.storage') . '/' . ltrim($config['path'] ?? 'logs/app.log', '/');
                $maxFiles = $config['max_files'] ?? 7;
                $this->ensureLogDirectoryExists(dirname($path));
                return new RotatingFileHandler($path, $maxFiles, $level);
                
            case 'syslog':
                return new \Monolog\Handler\SyslogHandler(
                    $config['ident'] ?? 'nexaphp',
                    $config['facility'] ?? LOG_USER,
                    $level
                );
                
            case 'slack':
                return new \Monolog\Handler\SlackWebhookHandler(
                    $config['webhook_url'] ?? '',
                    $config['channel'] ?? null,
                    $config['username'] ?? 'Monolog',
                    $config['use_attachment'] ?? true,
                    $config['icon_emoji'] ?? null,
                    $level
                );
                
            default:
                return null;
        }
    }

    /**
     * Get the log formatter.
     *
     * @return LineFormatter
     */
    protected function getLogFormatter(): LineFormatter
    {
        $format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        return new LineFormatter($format, 'Y-m-d H:i:s', true, true);
    }

    /**
     * Ensure the log directory exists.
     *
     * @param string $directory
     * @return void
     */
    protected function ensureLogDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}