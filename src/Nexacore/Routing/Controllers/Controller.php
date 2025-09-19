<?php
// src/Nexacore/Http/Controllers/Controller.php

namespace Nexacore\Http\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;

/**
 * Base Controller for NexaPHP
 *
 * @package Nexacore\Http\Controllers
 */
class Controller
{
    /**
     * The container instance.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Create a new controller instance.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Render a view.
     *
     * @param ResponseInterface $response
     * @param string $template
     * @param array $data
     * @return ResponseInterface
     */
    protected function view(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        return $this->container->get(Twig::class)->render($response, $template, $data);
    }

    /**
     * Return a JSON response.
     *
     * @param ResponseInterface $response
     * @param mixed $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function json(ResponseInterface $response, $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Redirect to a different URL.
     *
     * @param ResponseInterface $response
     * @param string $url
     * @param int $status
     * @return ResponseInterface
     */
    protected function redirect(ResponseInterface $response, string $url, int $status = 302): ResponseInterface
    {
        return $response
            ->withStatus($status)
            ->withHeader('Location', $url);
    }

    /**
     * Magic method to access container services.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->container->has($name)) {
            return $this->container->get($name);
        }

        throw new \RuntimeException("Service {$name} not found in container");
    }
}