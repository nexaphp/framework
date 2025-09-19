<?php
namespace Nexacore\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

class ValidateSignature implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->hasValidSignature($request)) {
            return $handler->handle($request);
        }

        $response = new Response(403);
        $response->getBody()->write('Invalid signature.');
        return $response;
    }

    protected function hasValidSignature(ServerRequestInterface $request): bool
    {
        $signature = $request->getQueryParams()['signature'] ?? '';
        $original = rtrim($request->getUri()->getPath(), '/') . '?' . 
                   http_build_query($request->getQueryParams());
        
        return hash_equals($signature, hash_hmac('sha256', $original, 'your-secret-key'));
    }
}