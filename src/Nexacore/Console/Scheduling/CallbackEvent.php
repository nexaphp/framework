<?php
// src/Nexacore/Console/Scheduling/CallbackEvent.php

namespace Nexacore\Console\Scheduling;

/**
 * Callback Event
 *
 * Represents a scheduled callback.
 *
 * @package Nexacore\Console\Scheduling
 */
class CallbackEvent extends Event
{
    /**
     * The callback to execute.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Create a new callback event instance.
     *
     * @param callable $callback
     * @param array $parameters
     */
    public function __construct(callable $callback, array $parameters = [])
    {
        $this->callback = $callback;
        $this->parameters = $parameters;
        $this->timezone = new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Run the scheduled callback.
     *
     * @return void
     */
    public function run(): void
    {
        try {
            $result = call_user_func_array($this->callback, $this->parameters);
            $this->output = is_string($result) ? $result : 'Callback executed successfully';
        } catch (\Exception $e) {
            $this->output = 'Error: ' . $e->getMessage();
        }
    }
}