<?php
namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

class SetCacheHeaders implements MiddlewareInterface
{
    protected $options = [];

    public function withParameters(array $parameters)
    {
        $this->options = $parameters;
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        foreach ($this->options as $key => $value) {
            $response = $response->withHeader($key, $value);
        }
        
        return $response;
    }
}