<?php
// src/Nexacore/Providers/RouteServiceProvider.php

namespace Nexacore\Providers;

use DI\Container;
use Nexacore\Contracts\Providers\ServiceProvider;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteParser;

/**
 * Enhanced Route Service Provider for NexaPHP
 *
 * Handles advanced route registration, caching, and route management.
 *
 * @package Nexacore\Providers
 */
class RouteServiceProvider implements ServiceProvider
{
    /**
     * The application instance.
     *
     * @var \Nexacore\Foundation\Application
     */
    protected $app;

    /**
     * The Slim application instance.
     *
     * @var App
     */
    protected $slim;

    /**
     * The route parser instance.
     *
     * @var RouteParser
     */
    protected $routeParser;

    /**
     * Route cache status.
     *
     * @var bool
     */
    protected $cacheEnabled = false;

    /**
     * Route cache path.
     *
     * @var string
     */
    protected $cachePath;

    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        $this->app = $container->get('nexa.app');
        $this->slim = $container->get('slim.app');
        $this->routeParser = $this->slim->getRouteCollector()->getRouteParser();

        // Load route configuration
        $config = $container->get('config');
        $this->cacheEnabled = $config['app']['env'] === 'production' && ($config['route']['cache'] ?? false);
        $this->cachePath = $this->app->storagePath('framework/cache/routes.php');

        // Register route services
        $container->set('router', function () {
            return $this->slim->getRouteCollector();
        });

        $container->set('route.parser', function () {
            return $this->routeParser;
        });

        $container->set('route.cache', function () use ($container) {
            return new class($this->cacheEnabled, $this->cachePath, $container) {
                protected $enabled;
                protected $path;
                protected $container;

                public function __construct(bool $enabled, string $path, Container $container)
                {
                    $this->enabled = $enabled;
                    $this->path = $path;
                    $this->container = $container;
                }

                public function isEnabled(): bool
                {
                    return $this->enabled;
                }

                public function getPath(): string
                {
                    return $this->path;
                }

                public function clear(): void
                {
                    if (file_exists($this->path)) {
                        unlink($this->path);
                    }
                }

                public function load(): ?array
                {
                    if ($this->enabled && file_exists($this->path)) {
                        return require $this->path;
                    }
                    return null;
                }

                public function save(array $routes): void
                {
                    if ($this->enabled) {
                        $content = "<?php\n\nreturn " . var_export($routes, true) . ";\n";
                        file_put_contents($this->path, $content);
                    }
                }
            };
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        $this->loadRoutes();

        // Register route helpers in container
        $this->registerRouteHelpers($container);
    }

    /**
     * Load application routes.
     *
     * @return void
     */
    protected function loadRoutes(): void
    {
        if ($this->loadCachedRoutes()) {
            return;
        }

        $this->loadRouteFiles();

        if ($this->cacheEnabled) {
            $this->cacheRoutes();
        }
    }

    /**
     * Load cached routes if available.
     *
     * @return bool
     */
    protected function loadCachedRoutes(): bool
    {
        $cachedRoutes = $this->app->getContainer()->get('route.cache')->load();

        if ($cachedRoutes) {
            foreach ($cachedRoutes as $route) {
                $this->createRouteFromCache($route);
            }
            return true;
        }

        return false;
    }

    /**
     * Create a route from cached data.
     *
     * @param array $routeData
     * @return void
     */
    protected function createRouteFromCache(array $routeData): void
    {
        $method = $routeData['method'];
        $pattern = $routeData['pattern'];
        $callable = $routeData['callable'];
        $middleware = $routeData['middleware'] ?? [];

        $route = $this->slim->$method($pattern, $callable);

        foreach ($middleware as $middlewareClass) {
            $route->add($this->app->getContainer()->get($middlewareClass));
        }

        if (isset($routeData['name'])) {
            $route->setName($routeData['name']);
        }
    }

    /**
     * Load route files from configuration.
     *
     * @return void
     */
    protected function loadRouteFiles(): void
    {
        $config = $this->app->get('config');
        $routeFiles = $config['route']['files'] ?? ['web.php', 'api.php', 'auth.php'];

        foreach ($routeFiles as $file) {
            $routePath = $this->app->routesPath($file);
            if (file_exists($routePath)) {
                require $routePath;
            }
        }
    }

    /**
     * Cache the application routes.
     *
     * @return void
     */
    protected function cacheRoutes(): void
    {
        $routeCollector = $this->slim->getRouteCollector();
        $routes = $routeCollector->getRoutes();
        $cachedRoutes = [];

        foreach ($routes as $route) {
            $cachedRoutes[] = [
                'method' => $route->getMethods()[0],
                'pattern' => $route->getPattern(),
                'callable' => $route->getCallable(),
                'middleware' => $this->getRouteMiddleware($route),
                'name' => $route->getName(),
            ];
        }

        $this->app->getContainer()->get('route.cache')->save($cachedRoutes);
    }

    /**
     * Get middleware for a route.
     *
     * @param \Slim\Interfaces\RouteInterface $route
     * @return array
     */
    protected function getRouteMiddleware($route): array
    {
        // This would need reflection to access protected middleware property
        // For now, return empty array as this is complex to implement
        return [];
    }

    /**
     * Register route helpers in the container.
     *
     * @param Container $container
     * @return void
     */
    protected function registerRouteHelpers(Container $container): void
    {
        $container->set('url', function () use ($container) {
            return new class($container) {
                protected $container;

                public function __construct(Container $container)
                {
                    $this->container = $container;
                }

                public function route(string $name, array $params = [], array $queryParams = []): string
                {
                    $routeParser = $this->container->get('route.parser');
                    return $routeParser->urlFor($name, $params, $queryParams);
                }

                public function asset(string $path): string
                {
                    $config = $this->container->get('config');
                    $baseUrl = rtrim($config['app']['url'] ?? '', '/');
                    return $baseUrl . '/assets/' . ltrim($path, '/');
                }

                public function current(): string
                {
                    $request = $this->container->get('request');
                    return (string) $request->getUri();
                }

                public function previous(): string
                {
                    $request = $this->container->get('request');
                    return $request->getHeaderLine('Referer') ?: '/';
                }
            };
        });
    }

    /**
     * Create a route group with shared attributes.
     *
     * @param string $pattern
     * @param callable $callback
     * @param array $options
     * @return void
     */
    public function group(string $pattern, callable $callback, array $options = []): void
    {
        $middleware = $options['middleware'] ?? [];
        $namespace = $options['namespace'] ?? '';

        $this->slim->group($pattern, function (RouteCollectorProxy $group) use ($callback, $namespace) {
            if ($namespace) {
                $previousNamespace = $this->app->getContainer()->get('route.namespace');
                $this->app->getContainer()->set('route.namespace', $namespace);
            }

            $callback($group);

            if ($namespace) {
                $this->app->getContainer()->set('route.namespace', $previousNamespace);
            }
        })->addMiddleware(
            $this->resolveMiddleware($middleware)
        );
    }

    /**
     * Create resource routes for a controller.
     *
     * @param string $pattern
     * @param string $controller
     * @param array $options
     * @return void
     */
    public function resource(string $pattern, string $controller, array $options = []): void
    {
        $name = $options['name'] ?? str_replace('/', '.', trim($pattern, '/'));
        $only = $options['only'] ?? ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        $middleware = $options['middleware'] ?? [];

        $routes = [
            'index' => ['GET', $pattern, 'index'],
            'create' => ['GET', $pattern . '/create', 'create'],
            'store' => ['POST', $pattern, 'store'],
            'show' => ['GET', $pattern . '/{id}', 'show'],
            'edit' => ['GET', $pattern . '/{id}/edit', 'edit'],
            'update' => ['PUT', $pattern . '/{id}', 'update'],
            'destroy' => ['DELETE', $pattern . '/{id}', 'destroy'],
        ];

        foreach ($routes as $method => $route) {
            if (in_array($method, $except) || (!empty($only) && !in_array($method, $only))) {
                continue;
            }

            [$httpMethod, $routePattern, $action] = $route;

            $this->slim->$httpMethod(
                $routePattern,
                [$controller, $action]
            )->setName($name . '.' . $method)->addMiddleware(
                $this->resolveMiddleware($middleware)
            );
        }
    }

    /**
     * Resolve middleware array to instances.
     *
     * @param array $middleware
     * @return array
     */
    protected function resolveMiddleware(array $middleware): array
    {
        $resolved = [];
        $container = $this->app->getContainer();

        foreach ($middleware as $middlewareItem) {
            if (is_string($middlewareItem)) {
                $resolved[] = $container->get($middlewareItem);
            } else {
                $resolved[] = $middlewareItem;
            }
        }

        return $resolved;
    }

    /**
     * Get all registered routes.
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->slim->getRouteCollector()->getRoutes();
    }

    /**
     * Get route by name.
     *
     * @param string $name
     * @return \Slim\Interfaces\RouteInterface|null
     */
    public function getRouteByName(string $name): ?\Slim\Interfaces\RouteInterface
    {
        foreach ($this->getRoutes() as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }
        return null;
    }
}