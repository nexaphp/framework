<?php

namespace Nexacore\Contracts\Providers;

interface ServiceProvider {
    public function register(\DI\Container $container): void;
    public function boot(\DI\Container $container): void;
}