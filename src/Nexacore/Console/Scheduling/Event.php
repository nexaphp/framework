<?php
// src/Nexacore/Console/Scheduling/Event.php

namespace Nexacore\Console\Scheduling;

use Cron\CronExpression;
use Symfony\Component\Process\Process;

/**
 * Scheduled Event
 *
 * Represents a scheduled console command.
 *
 * @package Nexacore\Console\Scheduling
 */
class Event
{
    /**
     * The command string.
     *
     * @var string
     */
    protected $command;

    /**
     * The command parameters.
     *
     * @var array
     */
    protected $parameters;

    /**
     * The cron expression.
     *
     * @var string
     */
    protected $expression = '* * * * *';

    /**
     * The timezone.
     *
     * @var \DateTimeZone
     */
    protected $timezone;

    /**
     * Create a new event instance.
     *
     * @param string $command
     * @param array $parameters
     */
    public function __construct(string $command, array $parameters = [])
    {
        $this->command = $command;
        $this->parameters = $parameters;
        $this->timezone = new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Set the cron expression.
     *
     * @param string $expression
     * @return $this
     */
    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Schedule the event to run every minute.
     *
     * @return $this
     */
    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    /**
     * Schedule the event to run every five minutes.
     *
     * @return $this
     */
    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    /**
     * Schedule the event to run every ten minutes.
     *
     * @return $this
     */
    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    /**
     * Schedule the event to run every thirty minutes.
     *
     * @return $this
     */
    public function everyThirtyMinutes(): self
    {
        return $this->cron('0,30 * * * *');
    }

    /**
     * Schedule the event to run hourly.
     *
     * @return $this
     */
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Schedule the event to run daily.
     *
     * @return $this
     */
    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    /**
     * Schedule the event to run at a specific time.
     *
     * @param string $time
     * @return $this
     */
    public function at(string $time): self
    {
        return $this->dailyAt($time);
    }

    /**
     * Schedule the event to run daily at a specific time.
     *
     * @param string $time
     * @return $this
     */
    public function dailyAt(string $time): self
    {
        $segments = explode(':', $time);
        $hour = $segments[0];
        $minute = $segments[1] ?? '0';
        
        return $this->cron("{$minute} {$hour} * * *");
    }

    /**
     * Schedule the event to run weekly.
     *
     * @return $this
     */
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Schedule the event to run monthly.
     *
     * @return $this
     */
    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    /**
     * Schedule the event to run quarterly.
     *
     * @return $this
     */
    public function quarterly(): self
    {
        return $this->cron('0 0 1 */3 *');
    }

    /**
     * Schedule the event to run yearly.
     *
     * @return $this
     */
    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    /**
     * Set the timezone.
     *
     * @param string $timezone
     * @return $this
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = new \DateTimeZone($timezone);
        return $this;
    }

    /**
     * Determine if the event is due to run.
     *
     * @return bool
     */
    public function isDue(): bool
    {
        $date = new \DateTime('now', $this->timezone);
        return CronExpression::factory($this->expression)->isDue($date);
    }

    /**
     * Run the scheduled command.
     *
     * @return void
     */
    public function run(): void
    {
        $command = $this->buildCommand();
        
        $process = Process::fromShellCommandline($command, base_path(), null, null, null);
        $process->run();
        
        if ($process->isSuccessful()) {
            $this->output = $process->getOutput();
        } else {
            $this->output = $process->getErrorOutput();
        }
    }

    /**
     * Build the command string.
     *
     * @return string
     */
    protected function buildCommand(): string
    {
        $command = 'php ' . base_path() . '/nexa ' . $this->command;
        
        foreach ($this->parameters as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $command .= " --{$key}";
                }
            } else {
                $command .= " --{$key}=\"" . addslashes($value) . "\"";
            }
        }
        
        return $command . ' > /dev/null 2>&1 &';
    }

    /**
     * Get the command output.
     *
     * @return string
     */
    public function getOutput(): string
    {
        return $this->output ?? '';
    }
}