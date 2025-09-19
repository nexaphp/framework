<?php
// src/Nexacore/Http/Middleware/TrustProxies.php

namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Trust Proxies Middleware
 *
 * Handles requests from load balancers and proxies.
 *
 * @package Nexacore\Http\Middleware
 */
class TrustProxies implements MiddlewareInterface
{
    /**
     * The trusted proxies.
     *
     * @var array
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers;

    /**
     * Process the request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->proxies = $request->getAttribute('config')['trusted.proxies'] ?? [];
        $this->headers = $request->getAttribute('config')['trusted.headers'] ?? 
            \Slim\Http\Factory\DecoratedResponseFactory::HEADER_X_FORWARDED_ALL;

        $request = $this->setTrustedProxyHeader($request);
        return $handler->handle($request);
    }

    /**
     * Set the trusted proxy header.
     *
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    protected function setTrustedProxyHeader(ServerRequestInterface $request): ServerRequestInterface
    {
        if (!empty($this->proxies)) {
            $request = $request->withAttribute('trusted_proxies', $this->proxies);
        }

        if ($this->headers) {
            $request = $request->withAttribute('trusted_headers', $this->headers);
        }

        return $request;
    }
}