<?php
/**
 * NexaPHP Application wrapper (app.php)
 *
 * - Provides a Laravel-like application class for a Slim 4 project.
 * - Integrates PHP-DI, Slim Bridge, Twig view, Monolog and common middleware.
 * - Defines standard paths (base, app, config, public, storage, views, logs)
 *
 * Usage:
 *   $app = NexaPHP\App::create(__DIR__);
 *   $slim = $app->getSlimApp();
 *   $slim->get('/hello', fn($req, $res) => $res->getBody()->write('Hello'));
 *   $slim->run();
 *
 * Requirements (composer packages):
 *  - slim/slim:^4
 *  - php-di/php-di:^6
 *  - php-di/slim-bridge
 *  - slim/twig-view
 *  - monolog/monolog
 *  - psr/log, nyholm/psr7 or laminas/laminas-diactoros for PSR-7 implementation
 *
 */

namespace NexaPHP;

use DI\Container;
use DI\ContainerBuilder;
use DI\Bridge\Slim\Bridge as SlimBridge;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;
use Slim\App as SlimApp;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\ErrorMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

class App
{
    /** @var string */
    protected string $basePath;

    /** @var array<string,string> */
    protected array $paths = [];

    /** @var Container */
    protected Container $container;

    /** @var SlimApp */
    protected SlimApp $slim;

    /** @var Logger */
    protected Logger $logger;

    /**
     * Create an application instance
     *
     * @param string $basePath Path to project root (where composer.json lives)
     * @param array $options Optional overrides:
     *   - env: array of environment variables to set
     *   - container: DI ContainerBuilder options
     */
    public static function create(string $basePath, array $options = []): self
    {
        return new self($basePath, $options);
    }

    /**
     * App constructor: build paths, container, logger, slim app
     *
     * @param string $basePath
     * @param array $options
     */
    public function __construct(string $basePath, array $options = [])
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->definePaths();

        // Optionally set environment variables from $options
        if (isset($options['env']) && is_array($options['env'])) {
            foreach ($options['env'] as $k => $v) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
            }
        }

        // Build container
        $this->buildContainer($options['container'] ?? []);

        // Initialize core services
        $this->setupLogger();
        $this->setupTwig();

        // Create Slim app via PHP-DI bridge
        $this->slim = SlimBridge::create($this->container);

        // Register middleware pipeline
        $this->registerDefaultMiddleware();

        // Bind commonly used instances into container if not already
        $this->container->set(App::class, $this);
        $this->container->set('config.paths', $this->paths);
    }

    /**
     * Define standard paths (Laravel-like)
     */
    protected function definePaths(): void
    {
        $this->paths = [
            'base'    => $this->basePath,
            'app'     => $this->basePath . DIRECTORY_SEPARATOR . 'app',
            'config'  => $this->basePath . DIRECTORY_SEPARATOR . 'config',
            'public'  => $this->basePath . DIRECTORY_SEPARATOR . 'public',
            'resources' => $this->basePath . DIRECTORY_SEPARATOR . 'resources',
            'views'   => $this->basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
            'storage' => $this->basePath . DIRECTORY_SEPARATOR . 'storage',
            'logs'    => $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
            'cache'   => $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache',
        ];

        // Ensure directories exist (best-effort)
        foreach ($this->paths as $p) {
            if (!is_dir($p)) {
                @mkdir($p, 0755, true);
            }
        }
    }

    /**
     * Build PHP-DI container
     *
     * @param array \$containerOptions Options forwarded to ContainerBuilder
     */
    protected function buildContainer(array $containerOptions = []): void
    {
        $builder = new ContainerBuilder();
        if (!empty($containerOptions['definitions'])) {
            $builder->addDefinitions($containerOptions['definitions']);
        }
        if (!empty($containerOptions['autowire']) && $containerOptions['autowire'] === false) {
            // noop: default autowiring remains
        }

        // Enable compilation if prod
        if (getenv('APP_ENV') === 'production' && !empty($containerOptions['compile'])) {
            $compilePath = $this->paths['cache'] . DIRECTORY_SEPARATOR . 'php-di';
            $builder->enableCompilation($compilePath);
        }

        $this->container = $builder->build();
    }

    /**
     * Setup Monolog logger and register in container
     * - RotatingFileHandler (daily) to storage/logs/nexaphp.log
     * - StreamHandler for stdout (useful in containers)
     */
    protected function setupLogger(): void
    {
        $name = getenv('APP_NAME') ?: 'nexaphp';
        $level = Logger::DEBUG;
        $logFile = $this->paths['logs'] . DIRECTORY_SEPARATOR . 'nexaphp.log';

        $logger = new Logger($name);
        $logger->pushProcessor(new UidProcessor());

        // Rotating file keeps daily logs
        $rotating = new RotatingFileHandler($logFile, 7, $level);
        $stream = new StreamHandler('php://stdout', $level);

        $logger->pushHandler($rotating);
        $logger->pushHandler($stream);

        $this->logger = $logger;

        // bind logger into container
        if ($this->container instanceof Container) {
            $this->container->set(LoggerInterface::class, $logger);
            $this->container->set(Logger::class, $logger);
        }
    }

    /**
     * Setup Twig view and register middleware
     */
    protected function setupTwig(): void
    {
        $viewsPath = $this->paths['views'];
        if (!is_dir($viewsPath)) {
            @mkdir($viewsPath, 0755, true);
        }

        $twig = Twig::create($viewsPath, ['cache' => $this->paths['cache'] . DIRECTORY_SEPARATOR . 'twig']);

        // Register in container for later retrieval
        if ($this->container instanceof Container) {
            $this->container->set(Twig::class, $twig);
        }

        // Add Twig middleware to Slim if already created
        if (isset($this->slim) && $this->slim instanceof SlimApp) {
            $this->slim->add(TwigMiddleware::createFromContainer($this->slim, Twig::class));
        }
    }

    /**
     * Register the default middleware stack
     */
    protected function registerDefaultMiddleware(): void
    {
        // Must add routing middleware for Slim 4
        $this->slim->addRoutingMiddleware();

        // Body parsing
        if (class_exists('\Slim\Middleware\BodyParsingMiddleware')) {
            $this->slim->addBodyParsingMiddleware();
        }

        // Error handling middleware
        $displayErrorDetails = (bool) (getenv('APP_DEBUG') === 'true' || getenv('APP_ENV') === 'development');
        $logErrors = true;
        $logErrorDetails = true;

        $errorMiddleware = new ErrorMiddleware(
            $this->slim->getCallableResolver(),
            $this->slim->getResponseFactory(),
            $displayErrorDetails,
            $logErrors,
            $logErrorDetails
        );

        // Example: you can add a custom error handler for 404
        $errorMiddleware->setErrorHandler(
            HttpNotFoundException::class,
            function ($request, $exception, $displayErrorDetails, $logErrors) {
                $responseFactory = $this->slim->getResponseFactory();
                $response = $responseFactory->createResponse(404);
                $response->getBody()->write('Not Found');
                return $response;
            }
        );

        $this->slim->add($errorMiddleware);

        // Optionally add logging middleware that uses our logger
        if ($this->container->has(LoggerInterface::class)) {
            $logger = $this->container->get(LoggerInterface::class);
            $this->slim->add(function ($request, $handler) use ($logger) {
                $logger->info('http.request', [
                    'method' => $request->getMethod(),
                    'uri' => (string)$request->getUri(),
                ]);

                return $handler->handle($request);
            });
        }

        // CORS or other middleware can be registered by the user using registerMiddleware()
    }

    /**
     * Register an arbitrary middleware (callable or PSR-15)
     *
     * @param callable|object $middleware
     */
    public function registerMiddleware($middleware): void
    {
        $this->slim->add($middleware);
    }

    /**
     * Attach additional service definitions to the container
     *
     * @param array $definitions
     */
    public function addDefinitions(array $definitions): void
    {
        if ($this->container instanceof Container) {
            // Note: PHP-DI Container doesn't have addDefinitions after build, so we recompile a new builder
            $builder = new ContainerBuilder();
            $builder->addDefinitions($definitions);
            // Merge existing container entries by rebuilding isn't trivial; simple approach: set each key value
            foreach ($definitions as $key => $val) {
                $this->container->set($key, $val);
            }
        }
    }

    /**
     * Get the underlying Slim app instance
     */
    public function getSlimApp(): SlimApp
    {
        return $this->slim;
    }

    /**
     * Get the DI container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get Monolog logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Helper to register routes file
     * Expects a routes file that accepts the Slim app as argument: function(SlimApp $app) { ... }
     */
    public function loadRoutes(string $routesFile): void
    {
        if (file_exists($routesFile)) {
            $cb = require $routesFile;
            if (is_callable($cb)) {
                $cb($this->slim);
            }
        }
    }

    /**
     * Register service providers (simple implementation pattern)
     * Each provider is an object with a register(Container \$container) method.
     */
    public function registerProvider(object $provider): void
    {
        if (method_exists($provider, 'register')) {
            $provider->register($this->container);
        }
    }

    /**
     * Simple config loader (PHP returning array from config/*.php)
     */
    public function config(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        $configFile = $this->paths['config'] . DIRECTORY_SEPARATOR . $file . '.php';

        if (!file_exists($configFile)) return $default;

        $config = require $configFile;
        foreach ($parts as $p) {
            if (!is_array($config) || !array_key_exists($p, $config)) return $default;
            $config = $config[$p];
        }

        return $config;
    }

}

// End of NexaPHP App class
