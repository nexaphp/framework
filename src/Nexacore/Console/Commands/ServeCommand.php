<?php
// src/Nexacore/Console/Commands/ServeCommand.php

namespace Nexacore\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ServeCommand extends Command
{
    protected static $defaultName = 'serve';
    protected static $defaultDescription = 'Serve the application on the PHP development server';

    protected function configure(): void
    {
        $this->setDescription('Serve the application on the PHP development server')
             ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', '127.0.0.1')
             ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on', '8000')
             ->addOption('public', null, InputOption::VALUE_OPTIONAL, 'The public directory to serve', 'public');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $public = $input->getOption('public');

        $output->writeln("NexaPHP development server started on <http://{$host}:{$port}>");
        $output->writeln("Press Ctrl+C to stop the server");

        $command = ["php", "-S", "{$host}:{$port}", "-t", $public];
        $process = new Process($command, base_path(), null, null, null);

        try {
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}