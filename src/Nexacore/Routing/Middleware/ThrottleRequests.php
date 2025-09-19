<?php
// src/Nexacore/Http/Middleware/ThrottleRequests.php

namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

/**
 * Throttle Requests Middleware
 *
 * Limits the number of requests a client can make in a given time period.
 *
 * @package Nexacore\Http\Middleware
 */
class ThrottleRequests implements MiddlewareInterface
{
    protected $maxAttempts;
    protected $decayMinutes;
    protected $cache;

    /**
     * Create a new middleware instance.
     *
     * @param int $maxAttempts
     * @param int $decayMinutes
     */
    public function withParameters(array $parameters)
    {
        $this->maxAttempts = $parameters[0] ?? 60;
        $this->decayMinutes = $parameters[1] ?? 1;
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->cache = $request->getAttribute('cache');
        $key = $this->resolveRequestSignature($request);

        if ($this->tooManyAttempts($key)) {
            return $this->buildResponse($key);
        }

        $this->incrementAttempts($key);
        
        $response = $handler->handle($request);
        
        return $this->addHeaders($response, $key);
    }

    protected function resolveRequestSignature(ServerRequestInterface $request): string
    {
        return sha1(
            $request->getMethod() .
            '|' . $request->getUri()->getHost() .
            '|' . $request->getUri()->getPath() .
            '|' . ($request->getServerParams()['REMOTE_ADDR'] ?? '')
        );
    }

    protected function tooManyAttempts(string $key): bool
    {
        $attempts = $this->cache->get($key, 0);
        return $attempts >= $this->maxAttempts;
    }

    protected function incrementAttempts(string $key): void
    {
        $this->cache->set($key, 1, $this->decayMinutes * 60);
        $attempts = $this->cache->get($key, 0);
        $this->cache->set($key, $attempts + 1, $this->decayMinutes * 60);
    }

    protected function buildResponse(string $key): ResponseInterface
    {
        $retryAfter = $this->getTimeUntilNextRetry($key);
        
        $response = new Response(429);
        $response->getBody()->write('Too Many Attempts.');
        
        return $this->addHeaders(
            $response,
            $key,
            $retryAfter
        );
    }

    protected function getTimeUntilNextRetry(string $key): int
    {
        return $this->cache->getTtl($key) ?? 0;
    }

    protected function addHeaders(ResponseInterface $response, string $key, ?int $retryAfter = null): ResponseInterface
    {
        $maxAttempts = $this->maxAttempts;
        $remaining = max(0, $maxAttempts - $this->cache->get($key, 0));

        if ($retryAfter === null) {
            $retryAfter = $this->getTimeUntilNextRetry($key);
        }

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) (time() + $retryAfter))
            ->withHeader('Retry-After', (string) $retryAfter);
    }
}