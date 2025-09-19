<?php
// src/Nexacore/Http/Kernel.php

namespace Nexacore\Http;

use Nexacore\Foundation\Nexa;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Container\ContainerInterface;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;

/**
 * NexaPHP HTTP Kernel
 *
 * Handles the HTTP request/response lifecycle, middleware stack,
 * and route dispatching for the NexaPHP framework.
 *
 * @package Nexacore\Http
 */
class Kernel
{
    /**
     * The application instance.
     *
     * @var Nexa
     */
    protected $app;

    /**
     * The Slim application instance.
     *
     * @var SlimApp
     */
    protected $slim;

    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [];

    /**
     * Create a new HTTP kernel instance.
     *
     * @param Nexa $app
     */
    public function __construct(Nexa $app)
    {
        $this->app = $app;
        $this->slim = $app->getSlim();
        $this->defineMiddleware();
    }

    /**
     * Define the application's middleware.
     *
     * @return void
     */
    protected function defineMiddleware(): void
    {
        $config = $this->app->get('config');
        
        $this->middleware = $config['middleware']['global'] ?? [
            \Nexacore\Http\Middleware\TrustProxies::class,
            \Nexacore\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Nexacore\Http\Middleware\TrimStrings::class,
            \Nexacore\Http\Middleware\EncryptCookies::class,
            \Nexacore\Http\Middleware\VerifyCsrfToken::class,
        ];

        $this->middlewareGroups = $config['middleware']['groups'] ?? [
            'web' => [
                \Nexacore\Http\Middleware\EncryptCookies::class,
                \Nexacore\Http\Middleware\VerifyCsrfToken::class,
                \Nexacore\Http\Middleware\ShareSessionData::class,
            ],
            'api' => [
                \Nexacore\Http\Middleware\ThrottleRequests::class . ':60,1',
                \Nexacore\Http\Middleware\ForceJsonResponse::class,
                \Nexacore\Http\Middleware\CorsMiddleware::class,
            ],
        ];

        $this->routeMiddleware = $config['middleware']['route'] ?? [
            'auth' => \Nexacore\Http\Middleware\Authenticate::class,
            'guest' => \Nexacore\Http\Middleware\RedirectIfAuthenticated::class,
            'throttle' => \Nexacore\Http\Middleware\ThrottleRequests::class,
            'signed' => \Nexacore\Http\Middleware\ValidateSignature::class,
            'cache.headers' => \Nexacore\Http\Middleware\SetCacheHeaders::class,
            'verified' => \Nexacore\Http\Middleware\EnsureEmailIsVerified::class,
        ];
    }

    /**
     * Handle an incoming HTTP request.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Bootstrap the application
            $this->app->boot();

            // Add application middleware
            $this->addApplicationMiddleware();

            // Add error middleware
            $this->addErrorMiddleware();

            // Handle the request through Slim
            $response = $this->slim->handle($request);

        } catch (HttpNotFoundException $e) {
            // Handle 404 errors
            $response = $this->renderNotFound($request);
        } catch (\Exception $e) {
            // Handle other exceptions
            $response = $this->renderException($request, $e);
        }

        return $response;
    }

    /**
     * Add application middleware to the Slim stack.
     *
     * @return void
     */
    protected function addApplicationMiddleware(): void
    {
        // Add global middleware
        foreach ($this->middleware as $middleware) {
            $this->slim->add($this->app->getContainer()->get($middleware));
        }
    }

    /**
     * Add error middleware to the application.
     *
     * @return void
     */
    protected function addErrorMiddleware(): void
    {
        $config = $this->app->get('config');
        $displayErrorDetails = $config['app']['debug'] ?? false;

        $errorMiddleware = $this->slim->addErrorMiddleware(
            $displayErrorDetails,
            true,
            true
        );

        // Custom error handler
        $errorMiddleware->setDefaultErrorHandler(
            function (
                ServerRequestInterface $request,
                \Throwable $exception,
                bool $displayErrorDetails,
                bool $logErrors,
                bool $logErrorDetails
            ) {
                return $this->handleException(
                    $request,
                    $exception,
                    $displayErrorDetails,
                    $logErrors,
                    $logErrorDetails
                );
            }
        );
    }

    /**
     * Handle an exception and return a response.
     *
     * @param ServerRequestInterface $request
     * @param \Throwable $exception
     * @param bool $displayErrorDetails
     * @param bool $logErrors
     * @param bool $logErrorDetails
     * @return ResponseInterface
     */
    protected function handleException(
        ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        // Log the exception
        if ($logErrors) {
            $this->logException($exception);
        }

        // Render appropriate error response
        if ($exception instanceof HttpNotFoundException) {
            return $this->renderNotFound($request);
        }

        // Default error response
        $response = new Response();
        $response->getBody()->write(
            $this->renderHttpError($exception, $displayErrorDetails)
        );

        return $response->withStatus(500);
    }

    /**
     * Render a 404 Not Found response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function renderNotFound(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(404);
        
        if ($this->app->getContainer()->has('view')) {
            try {
                return $this->app->view->render($response, 'errors/404.twig');
            } catch (\Exception $e) {
                // Fall through to JSON response
            }
        }

        // JSON response for API requests
        if ($request->getHeaderLine('Accept') === 'application/json') {
            $response->getBody()->write(json_encode([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Plain text fallback
        $response->getBody()->write('404 Not Found');
        return $response;
    }

    /**
     * Render an exception response.
     *
     * @param ServerRequestInterface $request
     * @param \Exception $exception
     * @return ResponseInterface
     */
    protected function renderException(ServerRequestInterface $request, \Exception $exception): ResponseInterface
    {
        $response = new Response(500);
        
        if ($this->app->getContainer()->has('view') && !$this->app->isProduction()) {
            try {
                return $this->app->view->render($response, 'errors/500.twig', [
                    'exception' => $exception
                ]);
            } catch (\Exception $e) {
                // Fall through to JSON response
            }
        }

        // JSON response for API requests
        if ($request->getHeaderLine('Accept') === 'application/json') {
            $response->getBody()->write(json_encode([
                'error' => 'Server Error',
                'message' => $this->app->isProduction() 
                    ? 'An unexpected error occurred.' 
                    : $exception->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Plain text fallback
        $response->getBody()->write(
            $this->app->isProduction() 
                ? '500 Internal Server Error' 
                : 'Error: ' . $exception->getMessage()
        );
        return $response;
    }

    /**
     * Render HTTP error content.
     *
     * @param \Throwable $exception
     * @param bool $displayErrorDetails
     * @return string
     */
    protected function renderHttpError(\Throwable $exception, bool $displayErrorDetails): string
    {
        if ($displayErrorDetails) {
            return sprintf(
                "<h1>500 Internal Server Error</h1>\n" .
                "<h2>%s</h2>\n" .
                "<p>%s</p>\n" .
                "<pre>%s</pre>",
                get_class($exception),
                $exception->getMessage(),
                $exception->getTraceAsString()
            );
        }

        return "<h1>500 Internal Server Error</h1>\n" .
               "<p>An unexpected error occurred. Please try again later.</p>";
    }

    /**
     * Log an exception.
     *
     * @param \Throwable $exception
     * @return void
     */
    protected function logException(\Throwable $exception): void
    {
        if ($this->app->getContainer()->has('logger')) {
            $this->app->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        } else {
            error_log(
                sprintf(
                    "NexaPHP Error: %s in %s on line %d\n%s",
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine(),
                    $exception->getTraceAsString()
                )
            );
        }
    }

    /**
     * Get the global middleware stack.
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get the route middleware groups.
     *
     * @return array
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Get the route middleware.
     *
     * @return array
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    /**
     * Terminate the request.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return void
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        // Perform any termination logic here
        // This is called after the response is sent to the client
        
        // Close database connections if they exist
        if ($this->app->getContainer()->has('db')) {
            $this->app->db->getConnection()->close();
        }
        
        // Write session data if session exists
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // Log request completion
        if ($this->app->getContainer()->has('logger')) {
            $this->app->logger->info('Request completed', [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'status' => $response->getStatusCode(),
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Get the Slim application instance.
     *
     * @return SlimApp
     */
    public function getSlim(): SlimApp
    {
        return $this->slim;
    }

    protected function logException(\Throwable $exception): void
{
    $logger = $this->app->getContainer()->has('logger') 
        ? $this->app->getContainer()->get('logger') 
        : null;

    if ($logger) {
        $logLevel = $this->getLogLevelForException($exception);
        
        $logger->log($logLevel, $exception->getMessage(), [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'type' => get_class($exception),
        ]);
    } else {
        error_log(
            sprintf(
                "NexaPHP Error: %s in %s on line %d\n%s",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            )
        );
    }
}

/**
 * Get the appropriate log level for an exception.
 *
 * @param \Throwable $exception
 * @return string
 */
protected function getLogLevelForException(\Throwable $exception): string
{
    if ($exception instanceof HttpNotFoundException) {
        return \Monolog\Logger::NOTICE;
    }
    
    if ($exception instanceof \PDOException) {
        return \Monolog\Logger::ALERT;
    }
    
    if ($exception instanceof \RuntimeException) {
        return \Monolog\Logger::ERROR;
    }
    
    return \Monolog\Logger::CRITICAL;
}

}