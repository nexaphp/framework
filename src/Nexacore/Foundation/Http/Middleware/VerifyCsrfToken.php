<?php
// src/Nexacore/Http/Middleware/VerifyCsrfToken.php

namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

/**
 * Verify CSRF Token Middleware
 *
 * Protects against CSRF attacks by validating tokens on POST requests.
 *
 * @package Nexacore\Http\Middleware
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [];

    /**
     * Process the request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->shouldPassThrough($request) || $this->tokensMatch($request)) {
            return $handler->handle($request);
        }

        return $this->handleTokenMismatch($request);
    }

    /**
     * Determine if the request should pass through.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function shouldPassThrough(ServerRequestInterface $request): bool
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Skip for read operations and API requests
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS']) || 
            strpos($path, '/api/') === 0) {
            return true;
        }

        // Check except list
        foreach ($this->except as $except) {
            if ($path === $except) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the CSRF tokens match.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function tokensMatch(ServerRequestInterface $request): bool
    {
        $session = $request->getAttribute('session');
        $token = $this->getTokenFromRequest($request);

        return $session && $token && hash_equals($session->get('csrf_token'), $token);
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    protected function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $params = $request->getParsedBody();
        $token = $params['_token'] ?? $request->getHeaderLine('X-CSRF-TOKEN');

        return $token ?: null;
    }

    /**
     * Handle a CSRF token mismatch.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function handleTokenMismatch(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getHeaderLine('Accept') === 'application/json') {
            $response = new Response(419);
            $response->getBody()->write(json_encode([
                'error' => 'CSRF Token Mismatch',
                'message' => 'The CSRF token is invalid or missing.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response = new Response(419);
        $response->getBody()->write('CSRF token mismatch. Please try again.');
        return $response;
    }
}