<?php
// src/Nexacore/Routing/RouteMacros.php

namespace Nexacore\Routing;

use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\Route;

/**
 * Route Macros for NexaPHP
 *
 * Provides fluent, expressive route definitions with advanced features.
 *
 * @package Nexacore\Routing
 */
class RouteMacros
{
    /**
     * Register route macros.
     *
     * @param \Nexacore\Foundation\Application $app
     * @return void
     */
    public static function register(\Nexacore\Foundation\Application $app): void
    {
        $routeCollector = $app->getSlim()->getRouteCollector();
        
        // Add macro methods to RouteCollectorProxy
        static::registerCollectorMacros($routeCollector, $app);
        
        // Add macro methods to Route
        static::registerRouteMacros($routeCollector, $app);
    }

    /**
     * Register macros for RouteCollectorProxy.
     *
     * @param mixed $collector
     * @param \Nexacore\Foundation\Application $app
     * @return void
     */
    protected static function registerCollectorMacros($collector, \Nexacore\Foundation\Application $app): void
    {
        // Resource macro
        $collector->macro('resource', function ($pattern, $controller, $options = []) use ($app) {
            $app->getContainer()->get(RouteServiceProvider::class)->resource($pattern, $controller, $options);
        });

        // Api resource macro (without create/edit routes)
        $collector->macro('apiResource', function ($pattern, $controller, $options = []) use ($app) {
            $options['except'] = ['create', 'edit'];
            $app->getContainer()->get(RouteServiceProvider::class)->resource($pattern, $controller, $options);
        });

        // Redirect macro
        $collector->macro('redirect', function ($from, $to, $status = 302) {
            return $this->get($from, function ($request, $response, $args) use ($to, $status) {
                return $response->withHeader('Location', $to)->withStatus($status);
            });
        });

        // View macro (directly render a view)
        $collector->macro('view', function ($pattern, $view, $data = []) use ($app) {
            return $this->get($pattern, function ($request, $response, $args) use ($view, $data, $app) {
                $viewData = array_merge($data, $args);
                return $app->getContainer()->get('view')->render($response, $view, $viewData);
            });
        });

        // Match multiple HTTP methods
        $collector->macro('match', function (array $methods, $pattern, $handler) {
            foreach ($methods as $method) {
                $this->$method($pattern, $handler);
            }
            return $this;
        });

        // Crud resource macro
        $collector->macro('crud', function ($pattern, $controller, $options = []) {
            $options['only'] = ['index', 'store', 'show', 'update', 'destroy'];
            return $this->resource($pattern, $controller, $options);
        });
    }

    /**
     * Register macros for Route.
     *
     * @param mixed $collector
     * @param \Nexacore\Foundation\Application $app
     * @return void
     */
    protected static function registerRouteMacros($collector, \Nexacore\Foundation\Application $app): void
    {
        // Where macro for parameter constraints
        Route::macro('where', function (array $constraints) {
            foreach ($constraints as $param => $pattern) {
                $this->setArgument($param, $pattern);
            }
            return $this;
        });

        // Name macro for fluent naming
        Route::macro('name', function (string $name) {
            return $this->setName($name);
        });

        // Middleware macro for fluent middleware
        Route::macro('middleware', function ($middleware) use ($app) {
            if (is_string($middleware)) {
                $middleware = [$middleware];
            }
            
            foreach ($middleware as $m) {
                $this->add($app->getContainer()->get($m));
            }
            return $this;
        });

        // WithoutMiddleware macro
        Route::macro('withoutMiddleware', function ($middleware) {
            // This would require tracking which middleware to remove
            return $this;
        });

        // Scope macro for nested groups
        Route::macro('scope', function (string $prefix, callable $callback) use ($app) {
            return $app->getContainer()->get(RouteServiceProvider::class)->group($prefix, $callback);
        });
    }
}