<?php
// src/Nexacore/Console/Commands/RouteListCommand.php

namespace Nexacore\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RouteListCommand extends Command
{
    protected static $defaultName = 'route:list';
    protected static $defaultDescription = 'List all registered routes';

    protected $app;

    public function __construct($app)
    {
        parent::__construct();
        $this->app = $app;
    }

    protected function configure(): void
    {
        $this->setDescription('List all registered routes')
             ->addOption('method', null, InputOption::VALUE_OPTIONAL, 'Filter by HTTP method')
             ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Filter by route name')
             ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Filter by path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routes = $this->app->getSlim()->getRouteCollector()->getRoutes();
        $table = new Table($output);
        
        $table->setHeaders(['Method', 'Path', 'Name', 'Handler']);
        
        $rows = [];
        foreach ($routes as $route) {
            $methods = implode('|', $route->getMethods());
            $pattern = $route->getPattern();
            $name = $route->getName() ?? '';
            $callable = $this->formatCallable($route->getCallable());
            
            $rows[] = [$methods, $pattern, $name, $callable];
        }
        
        $table->setRows($rows);
        $table->render();
        
        return Command::SUCCESS;
    }

    protected function formatCallable($callable): string
    {
        if (is_string($callable)) {
            return $callable;
        }
        
        if (is_array($callable)) {
            if (is_object($callable[0])) {
                return get_class($callable[0]) . '::' . $callable[1];
            }
            return $callable[0] . '::' . $callable[1];
        }
        
        if ($callable instanceof \Closure) {
            return 'Closure';
        }
        
        return 'Unknown';
    }
}