<?php

namespace App\bootstrap;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Src\Console\ConsoleRouteCompiler;
use Src\Console\ConsoleRouter;
use Src\DI\Container;
use Src\DI\ContainerCompiler;
use Src\DI\Interfaces\ToCompileContainer;
use Src\DI\NotFoundException;
use Src\Router\RouteCompiler;
use Src\Router\Router;

class CompilationManager
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    public function shouldCompile(): bool
    {
        return getenv('APP_ENV') === 'prod';
    }

    /**
     * @throws NotFoundException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function compile(ContainerInterface&ToCompileContainer $container): void
    {
        $router = $container->has(Router::class) ? $container->get(Router::class) : null;
        $consoleRouter = $container->has(ConsoleRouter::class) ? $container->get(ConsoleRouter::class) : null;
        $containerCompiler = new ContainerCompiler($container);
        $containerCompiler->compile(
            outputFile: $this->cacheDir . '/CachedContainer.php',
            namespace: 'App\\cache\\compiled',
            router: $router,
            consoleRouter: $consoleRouter
        );

        if ($router) {
            $routeCompiler = new RouteCompiler($router);
            $routeCompiler->compile(
                outputFile: $this->cacheDir . '/CompiledRoutes.php',
                namespace: 'App\\cache\\compiled'
            );
        }

        if ($consoleRouter) {
            $consoleRouterCompiler = new ConsoleRouteCompiler($consoleRouter);
            $consoleRouterCompiler->compile(
                outputFile: $this->cacheDir . '/CompiledConsoleRoutes.php',
                namespace: 'App\\cache\\compiled'
            );
        }
    }

    /**
     * @return ContainerInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function loadCompiled(): ContainerInterface
    {
        $containerFile = $this->cacheDir . '/CachedContainer.php';
        $routesFile = $this->cacheDir . '/CompiledRoutes.php';

        require $containerFile;
        require $routesFile;

        $container = new \App\cache\compiled\CachedContainer();
        $router = $container->get(Router::class);

        \App\cache\compiled\CompiledRoutes::loadIntoRouter($router);
        $consoleRouter = $container->get(ConsoleRouter::class);
        \App\cache\compiled\CompiledConsoleRoutes::loadIntoRouter($consoleRouter);


        return $container;
    }
}