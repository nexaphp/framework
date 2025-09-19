<?php
// src/Nexacore/Providers/RouteServiceProvider.php (Enhanced)

namespace Nexacore\Providers;

use DI\Container;
use Nexacore\Contracts\Providers\ServiceProvider;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class RouteServiceProvider implements ServiceProvider
{
    protected $app;
    protected $slim;
    protected $middlewareGroups = [];
    protected $routeMiddleware = [];

    public function register(Container $container): void
    {
        $this->app = $container->get('nexa.app');
        $this->slim = $container->get('slim.app');

        $this->loadMiddlewareConfiguration($container);
        $this->registerRouteMiddleware($container);
    }

    public function boot(Container $container): void
    {
        $this->loadRoutes();
    }

    /**
     * Load middleware configuration from config.
     *
     * @param Container $container
     * @return void
     */
    protected function loadMiddlewareConfiguration(Container $container): void
    {
        $config = $container->get('config');
        
        $this->middlewareGroups = $config['middleware']['groups'] ?? [];
        $this->routeMiddleware = $config['middleware']['route'] ?? [];
    }

    protected function registerRouteMiddleware(Container $container): void
    {
        foreach ($this->routeMiddleware as $key => $middleware) {
            $container->set("middleware.{$key}", function () use ($container, $middleware) {
                return $this->resolveMiddleware($container, $middleware);
            });
        }

        foreach ($this->middlewareGroups as $key => $middlewares) {
            $container->set("middleware.group.{$key}", function () use ($container, $middlewares) {
                return array_map(function ($middleware) use ($container) {
                    return $this->resolveMiddleware($container, $middleware);
                }, (array) $middlewares);
            });
        }
    }

    /**
     * Resolve middleware with parameters support.
     *
     * @param Container $container
     * @param string $middleware
     * @return mixed
     */
    protected function resolveMiddleware(Container $container, string $middleware)
    {
        // Check if middleware has parameters (e.g., 'throttle:60,1')
        if (strpos($middleware, ':') !== false) {
            [$middlewareClass, $parameters] = explode(':', $middleware, 2);
            $parameters = explode(',', $parameters);
            
            $middlewareInstance = $container->get($middlewareClass);
            
            // If middleware has a withParameters method, use it
            if (method_exists($middlewareInstance, 'withParameters')) {
                return $middlewareInstance->withParameters($parameters);
            }
            
            return $middlewareInstance;
        }

        return $container->get($middleware);
    }

    protected function loadRoutes(): void
    {
        $config = $this->app->get('config');
        $routesConfig = $config['routes'] ?? [];

        foreach ($routesConfig as $routeGroup) {
            $this->loadRouteGroup($routeGroup);
        }
    }

    /**
     * Load a route group from configuration.
     *
     * @param array $config
     * @return void
     */
    protected function loadRouteGroup(array $config): void
    {
        $path = $config['path'] ?? '';
        $prefix = $config['prefix'] ?? '';
        $middleware = $config['middleware'] ?? null;

        if (!file_exists($this->app->routesPath($path))) {
            return;
        }

        $this->slim->group($prefix, function (RouteCollectorProxy $group) use ($path) {
            require $this->app->routesPath($path);
        })->addMiddleware(
            $middleware ? $this->app->getContainer()->get("middleware.group.{$middleware}") ?? [] : []
        );
    }

    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }
}