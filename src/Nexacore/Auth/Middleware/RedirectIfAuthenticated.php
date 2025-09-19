<?php
// src/Nexacore/Http/Middleware/RedirectIfAuthenticated.php

namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

/**
 * Redirect If Authenticated Middleware
 *
 * Redirects authenticated users away from pages like login/register.
 *
 * @package Nexacore\Http\Middleware
 */
class RedirectIfAuthenticated implements MiddlewareInterface
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
            return $this->redirectToDashboard($request);
        }

        return $handler->handle($request);
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
     * Redirect to dashboard.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function redirectToDashboard(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(302);
        return $response->withHeader('Location', '/dashboard');
    }
}