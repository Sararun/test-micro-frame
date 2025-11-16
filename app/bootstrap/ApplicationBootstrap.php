<?php

namespace App\bootstrap;

use Psr\Container\ContainerInterface;
use Src\Console\ConsoleRouter;
use Src\DI\Container;
use Src\DI\Interfaces\ToCompileContainer;
use Src\Router\Router;

final class ApplicationBootstrap
{
    /**
     * @var ModuleManifest[]
     */
    private(set) array $modules = [];

    public function registerModule(ModuleManifest $manifest): self
    {
        $this->modules[] = $manifest;
        return $this;
    }

    public function init(): ContainerInterface&ToCompileContainer
    {
        $container = new Container();
        $container->singleton(Router::class);
        $router = $container->get(Router::class);
        $container->singleton(ConsoleRouter::class);
        $consoleRouter = $container->get(ConsoleRouter::class);

        foreach ($this->modules as $module) {
            $module->registerRoutes($router);
            foreach ($router->getRoutes() as $route) {
                foreach ($route->middlewares as $middleware) {
                    $container->bind($middleware);
                }
            }
            $module->registerCommands($consoleRouter);
            $module->registerServices($container);
        }
        return $container;
    }
}