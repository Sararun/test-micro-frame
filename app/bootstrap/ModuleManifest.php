<?php

namespace App\bootstrap;

use Src\Console\ConsoleRouter;
use Src\DI\Container;
use Src\Router\Router;

interface ModuleManifest
{
    public function registerServices(Container $container): void;

    public function registerRoutes(Router $router): void;

    public function registerCommands(ConsoleRouter $router): void;
}