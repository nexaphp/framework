<?php
// src/Nexacore/Http/Middleware/TrimStrings.php

namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Middleware to trim string values in request data
 *
 * @package Nexacore\Http\Middleware
 */
class TrimStrings implements MiddlewareInterface
{
    /**
     * The attributes that should not be trimmed.
     *
     * @var array
     */
    protected $except = [
        'password',
        'password_confirmation',
    ];

    /**
     * Process the request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $this->cleanRequestData($request);
        return $handler->handle($request);
    }

    /**
     * Clean the request's data.
     *
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    protected function cleanRequestData(ServerRequestInterface $request): ServerRequestInterface
    {
        $parsedBody = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        if (is_array($parsedBody)) {
            $parsedBody = $this->cleanArray($parsedBody);
            $request = $request->withParsedBody($parsedBody);
        }

        if (is_array($queryParams)) {
            $queryParams = $this->cleanArray($queryParams);
            $request = $request->withQueryParams($queryParams);
        }

        return $request;
    }

    /**
     * Clean the data in the given array.
     *
     * @param array $data
     * @return array
     */
    protected function cleanArray(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return $this->trim($value);
            } elseif (is_array($value)) {
                return $this->cleanArray($value);
            }
            return $value;
        }, $data);
    }

    /**
     * Trim the given string.
     *
     * @param string $value
     * @return string
     */
    protected function trim(string $value): string
    {
        return trim($value);
    }
}