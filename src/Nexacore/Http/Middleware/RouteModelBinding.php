<?php
// src/Nexacore/Http/Middleware/RouteModelBinding.php

namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Route Model Binding Middleware
 *
 * Automatically resolves route parameters to model instances.
 *
 * @package Nexacore\Http\Middleware
 */
class RouteModelBinding implements MiddlewareInterface
{
    /**
     * Process the request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $args = $route ? $route->getArguments() : [];
        $config = $request->getAttribute('config')['route']['route_model_binding'] ?? [];
        
        if (!$config['enabled'] ?? false) {
            return $handler->handle($request);
        }
        
        $patterns = $config['patterns'] ?? [];
        $resolvedArgs = [];
        
        foreach ($args as $key => $value) {
            if (isset($patterns[$key]) {
                $modelClass = $patterns[$key];
                $resolvedArgs[$key] = $this->resolveModel($modelClass, $value);
            } else {
                $resolvedArgs[$key] = $value;
            }
        }
        
        // Store resolved models in request attributes
        $request = $request->withAttribute('routeModels', $resolvedArgs);
        
        // Also set individual model attributes
        foreach ($resolvedArgs as $key => $model) {
            $request = $request->withAttribute($key, $model);
        }
        
        return $handler->handle($request);
    }
    
    /**
     * Resolve a model instance by ID.
     *
     * @param string $modelClass
     * @param mixed $value
     * @return object|null
     */
    protected function resolveModel(string $modelClass, $value): ?object
    {
        if (!class_exists($modelClass)) {
            return null;
        }
        
        return $modelClass::find($value);
    }
}