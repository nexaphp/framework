<?php
// src/Nexacore/Providers/ConfigServiceProvider.php

namespace Nexacore\Providers;

use DI\Container;
use Nexacore\Contracts\Providers\ServiceProvider;
 * Handles configuration loading, merging, and provides access
 * to configuration values throughout the application.
 *
 * @package Nexacore\Providers
 */
class ConfigServiceProvider implements ServiceProvider
{
    /**
     * The loaded configuration items.
     *
     * @var array
     */
    protected $config = [];

    /**
     * The configuration file paths.
     *
     * @var array
     */
    protected $configPaths = [];

    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        $this->configPaths = [
            'app' => $container->get('path.config'),
            'framework' => dirname(__DIR__, 2) . '/config', // Framework default configs
        ];

        $this->loadConfigurationFiles($container);

        $container->set('config', function () {
            return $this->config;
        });

        $this->registerConfigurationSingleton($container);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // Set default timezone from config
        if (isset($this->config['app']['timezone'])) {
            date_default_timezone_set($this->config['app']['timezone']);
        }

        // Set error reporting based on config
        if (isset($this->config['app']['debug'])) {
            if ($this->config['app']['debug']) {
                error_reporting(E_ALL);
                ini_set('display_errors', '1');
            } else {
                error_reporting(0);
                ini_set('display_errors', '0');
            }
        }
    }

    /**
     * Load all configuration files.
     *
     * @param Container $container
     * @return void
     */
    protected function loadConfigurationFiles(Container $container): void
    {
        $configPath = $container->get('path.config');

        if (!is_dir($configPath)) {
            throw new RuntimeException("Configuration directory not found: {$configPath}");
        }

        $files = $this->getConfigurationFiles($configPath);

        foreach ($files as $key => $path) {
            $this->config[$key] = require $path;
        }

        // Merge framework default configs if they exist
        $this->mergeFrameworkConfigs();

        // Apply environment overrides
        $this->applyEnvironmentOverrides();
    }

    /**
     * Get all configuration files from the config directory.
     *
     * @param string $configPath
     * @return array
     */
    protected function getConfigurationFiles(string $configPath): array
    {
        $files = [];
        $configDir = new \DirectoryIterator($configPath);

        foreach ($configDir as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $key = $file->getBasename('.php');
                $files[$key] = $file->getRealPath();
            }
        }

        return $files;
    }

    /**
     * Merge framework default configuration files.
     *
     * @return void
     */
    protected function mergeFrameworkConfigs(): void
    {
        $frameworkConfigPath = $this->configPaths['framework'];

        if (is_dir($frameworkConfigPath)) {
            $frameworkFiles = $this->getConfigurationFiles($frameworkConfigPath);

            foreach ($frameworkFiles as $key => $path) {
                $frameworkConfig = require $path;
                
                if (isset($this->config[$key]) && is_array($this->config[$key]) && is_array($frameworkConfig)) {
                    $this->config[$key] = array_merge($frameworkConfig, $this->config[$key]);
                } else {
                    $this->config[$key] = $frameworkConfig;
                }
            }
        }
    }

    /**
     * Apply environment-specific configuration overrides.
     *
     * @return void
     */
    protected function applyEnvironmentOverrides(): void
    {
        $env = $_ENV['APP_ENV'] ?? 'production';

        // Load environment-specific config files if they exist
        $envConfigPath = $this->configPaths['app'] . '/' . $env;
        
        if (is_dir($envConfigPath)) {
            $envFiles = $this->getConfigurationFiles($envConfigPath);

            foreach ($envFiles as $key => $path) {
                $envConfig = require $path;
                
                if (isset($this->config[$key]) && is_array($this->config[$key]) && is_array($envConfig)) {
                    $this->config[$key] = array_merge($this->config[$key], $envConfig);
                } else {
                    $this->config[$key] = $envConfig;
                }
            }
        }

        // Apply environment variables as config overrides
        $this->applyEnvVarsToConfig();
    }

    /**
     * Apply environment variables to configuration.
     *
     * @return void
     */
    protected function applyEnvVarsToConfig(): void
    {
        foreach ($_ENV as $key => $value) {
            if (strpos($key, 'CONFIG_') === 0) {
                $configKey = strtolower(substr($key, 7)); // Remove 'CONFIG_' prefix
                $configPath = explode('_', $configKey);
                
                $this->setConfigValue($this->config, $configPath, $value);
            }
        }
    }

    /**
     * Set a configuration value using dot notation.
     *
     * @param array &$config
     * @param array $path
     * @param mixed $value
     * @return void
     */
    protected function setConfigValue(array &$config, array $path, $value): void
    {
        $key = array_shift($path);

        if (empty($path)) {
            $config[$key] = $this->parseEnvValue($value);
        } else {
            if (!isset($config[$key]) || !is_array($config[$key])) {
                $config[$key] = [];
            }
            $this->setConfigValue($config[$key], $path, $value);
        }
    }

    /**
     * Parse environment variable value.
     *
     * @param string $value
     * @return mixed
     */
    protected function parseEnvValue(string $value)
    {
        // Handle boolean values
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        
        // Handle null values
        if ($value === 'null') return null;
        
        // Handle numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') === false ? (int) $value : (float) $value;
        }
        
        // Handle JSON values
        if (preg_match('/^\[.*\]$|^\{.*\}$/', $value)) {
            return json_decode($value, true);
        }
        
        return $value;
    }

    /**
     * Register configuration singleton with helper methods.
     *
     * @param Container $container
     * @return void
     */
    protected function registerConfigurationSingleton(Container $container): void
    {
        $container->set('config.manager', function () {
            return new class($this->config) {
                protected $config;

                public function __construct(array $config)
                {
                    $this->config = $config;
                }

                /**
                 * Get a configuration value.
                 *
                 * @param string $key
                 * @param mixed $default
                 * @return mixed
                 */
                public function get(string $key, $default = null)
                {
                    return $this->arrayGet($this->config, $key, $default);
                }

                /**
                 * Set a configuration value.
                 *
                 * @param string $key
                 * @param mixed $value
                 * @return void
                 */
                public function set(string $key, $value): void
                {
                    $this->arraySet($this->config, $key, $value);
                }

                /**
                 * Check if a configuration value exists.
                 *
                 * @param string $key
                 * @return bool
                 */
                public function has(string $key): bool
                {
                    return $this->arrayHas($this->config, $key);
                }

                /**
                 * Get all configuration.
                 *
                 * @return array
                 */
                public function all(): array
                {
                    return $this->config;
                }

                /**
                 * Get a value from an array using dot notation.
                 *
                 * @param array $array
                 * @param string $key
                 * @param mixed $default
                 * @return mixed
                 */
                protected function arrayGet(array $array, string $key, $default = null)
                {
                    if (isset($array[$key])) {
                        return $array[$key];
                    }

                    foreach (explode('.', $key) as $segment) {
                        if (!is_array($array) || !array_key_exists($segment, $array)) {
                            return $default;
                        }
                        $array = $array[$segment];
                    }

                    return $array;
                }

                /**
                 * Set an array value using dot notation.
                 *
                 * @param array &$array
                 * @param string $key
                 * @param mixed $value
                 * @return void
                 */
                protected function arraySet(array &$array, string $key, $value): void
                {
                    $keys = explode('.', $key);

                    while (count($keys) > 1) {
                        $key = array_shift($keys);

                        if (!isset($array[$key]) || !is_array($array[$key])) {
                            $array[$key] = [];
                        }

                        $array = &$array[$key];
                    }

                    $array[array_shift($keys)] = $value;
                }

                /**
                 * Check if an array has a key using dot notation.
                 *
                 * @param array $array
                 * @param string $key
                 * @return bool
                 */
                protected function arrayHas(array $array, string $key): bool
                {
                    if (isset($array[$key])) {
                        return true;
                    }

                    foreach (explode('.', $key) as $segment) {
                        if (!is_array($array) || !array_key_exists($segment, $array)) {
                            return false;
                        }
                        $array = $array[$segment];
                    }

                    return true;
                }
            };
        });
    }

    /**
     * Get the loaded configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the configuration paths.
     *
     * @return array
     */
    public function getConfigPaths(): array
    {
        return $this->configPaths;
    }
}