<?php

namespace Nexacore\Foundation;

use DI\Container;
use DI\ContainerBuilder;
use Slim\App;
use Slim\Factory\AppFactory;
use Nexacore\Contracts\Providers\ServiceProvider;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * NexaPHP Application Core
 *
 * Concrete implementation of the Application interface.
 * This class serves as the central wrapper for the Slim framework.
 *
 * @package Nexacore\Foundation
 */
class Nexa implements Application
{
    /**
     * The Slim application instance.
     *
     * @var App
     */
    protected $slim;

    /**
     * The DI container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The base path of the application.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Indicates if the application has been bootstrapped.
     *
     * @var bool
     */
    protected $bootstrapped = false;

    /**
     * All of the registered service providers.
     *
     * @var ServiceProvider[]
     */
    protected $loadedProviders = [];

    /**
     * Create a new NexaPHP application instance.
     *
     * @param string|null $basePath
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?: dirname(__DIR__, 3);
        $this->bootstrapContainer();
        $this->bootstrapSlim();
        $this->registerBaseBindings();
    }

    /**
     * Bootstrap the DI container.
     *
     * @return void
     */
    protected function bootstrapContainer(): void
    {
        $builder = new ContainerBuilder();
        
        if ($this->isProduction()) {
            $builder->enableCompilation($this->storagePath('framework/cache'));
        }
        
        $builder->addDefinitions($this->getDefaultContainerDefinitions());
        $this->container = $builder->build();
    }

    /**
     * Bootstrap the Slim application.
     *
     * @return void
     */
    protected function bootstrapSlim(): void
    {
        AppFactory::setContainer($this->container);
        $this->slim = AppFactory::create();
        
        $this->container->set(App::class, $this->slim);
        $this->container->set(Application::class, $this);
        $this->container->set(Nexa::class, $this);
    }

    /**
     * Register the basic bindings into the container.
     *
     * @return void
     */
    protected function registerBaseBindings(): void
    {
        $this->container->set('app', $this);
        $this->container->set(ContainerInterface::class, $this->container);
    }

    /**
     * Get the default container definitions.
     *
     * @return array
     */
    protected function getDefaultContainerDefinitions(): array
    {
        return [
            // Application paths
            'path.base' => $this->basePath(),
            'path.config' => $this->configPath(),
            'path.database' => $this->databasePath(),
            'path.public' => $this->publicPath(),
            'path.storage' => $this->storagePath(),
            'path.resources' => $this->resourcePath(),
            'path.routes' => $this->routesPath(),

            // Application instances
            'slim.app' => \DI\get(App::class),
            'nexa.app' => \DI\get(Nexa::class),
            
            // Framework configuration
            'config' => \DI\factory(function () {
                return require $this->configPath('app.php');
            }),
        ];
    }

    /**
     * Register a service provider with the application.
     *
     * @param string|ServiceProvider $provider
     * @param bool $force
     * @return ServiceProvider
     */
    public function register($provider, bool $force = false): ServiceProvider
    {
        if (($registered = $this->getProvider($provider)) && !$force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        if (!$provider instanceof ServiceProvider) {
            throw new RuntimeException(
                'Service provider must implement Nexacore\Foundation\Providers\ServiceProvider'
            );
        }

        $provider->register($this->container);

        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        $this->markAsRegistered($provider);

        return $provider;
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param string $provider
     * @return ServiceProvider
     */
    public function resolveProvider(string $provider): ServiceProvider
    {
        return new $provider();
    }

    /**
     * Mark the given provider as registered.
     *
     * @param ServiceProvider $provider
     * @return void
     */
    protected function markAsRegistered(ServiceProvider $provider): void
    {
        $this->loadedProviders[get_class($provider)] = $provider;
    }

    /**
     * Bootstrap the application's service providers.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        array_walk($this->loadedProviders, function ($provider) {
            $this->bootProvider($provider);
        });

        $this->bootstrapped = true;
    }

    /**
     * Boot the given service provider.
     *
     * @param ServiceProvider $provider
     * @return void
     */
    protected function bootProvider(ServiceProvider $provider): void
    {
        $provider->boot($this->container);
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param string|ServiceProvider $provider
     * @return ServiceProvider|null
     */
    public function getProvider($provider): ?ServiceProvider
    {
        $name = is_string($provider) ? $provider : get_class($provider);
        return $this->loadedProviders[$name] ?? null;
    }

    /**
     * Get the registered service provider instances.
     *
     * @return ServiceProvider[]
     */
    public function getProviders(): array
    {
        return $this->loadedProviders;
    }

    /**
     * Determine if the application has been bootstrapped.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->bootstrapped;
    }

    /**
     * Register all of the configured providers.
     *
     * @return void
     */
    public function registerConfiguredProviders(): void
    {
        $providers = $this->container->get('config')['providers'] ?? [];

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    // Implement all the path methods from the interface
    public function basePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, $path);
    }

    public function configPath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, 'config', $path);
    }

    public function databasePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, 'database', $path);
    }

    public function publicPath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, 'public', $path);
    }

    public function storagePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, 'storage', $path);
    }

    public function resourcePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, 'resources', $path);
    }

    public function routesPath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, 'routes', $path);
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function environment(): string
    {
        return $_ENV['APP_ENV'] ?? 'production';
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function runningInConsole(): bool
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    public function getSlim(): App
    {
        return $this->slim;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function setBasePath(string $path): void
    {
        $this->basePath = rtrim($path, DIRECTORY_SEPARATOR);
    }

    // Implement ContainerInterface methods
    public function get($id)
    {
        return $this->container->get($id);
    }

    public function has($id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Join the given paths together.
     *
     * @param string ...$paths
     * @return string
     */
    protected function joinPaths(...$paths): string
    {
        return implode(DIRECTORY_SEPARATOR, array_filter($paths));
    }
}