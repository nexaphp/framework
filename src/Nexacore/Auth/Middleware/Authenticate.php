<?php
// src/Nexacore/Http/Middleware/Authenticate.php

namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

/**
 * Authentication Middleware
 *
 * Protects routes that require authenticated users.
 * Redirects to login page for web requests, returns 401 for API requests.
 *
 * @package Nexacore\Http\Middleware
 */
class Authenticate implements MiddlewareInterface
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
        if ($this->isAuthenticated($request)) {
            return $handler->handle($request);
        }

        return $this->handleUnauthorized($request);
    }

    /**
     * Check if the user is authenticated.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isAuthenticated(ServerRequestInterface $request): bool
    {
        $session = $request->getAttribute('session');
        
        return $session && $session->get('authenticated') === true;
    }

    /**
     * Handle unauthorized access.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function handleUnauthorized(ServerRequestInterface $request): ResponseInterface
    {
        // Check if this is an API request
        if ($this->isApiRequest($request)) {
            $response = new Response(401);
            $response->getBody()->write(json_encode([
                'error' => 'Unauthorized',
                'message' => 'Authentication required'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Redirect to login for web requests
        $response = new Response(302);
        return $response->withHeader('Location', '/login')
                       ->withHeader('X-NexaPHP-Redirect', 'Login required');
    }

    /**
     * Check if the request is an API request.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isApiRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        return strpos($path, '/api/') === 0 ||
               $request->getHeaderLine('Accept') === 'application/json' ||
               $request->getHeaderLine('Content-Type') === 'application/json';
    }
}