<?php

namespace App\bootstrap;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Src\Console\ConsoleRouteCompiler;
use Src\Console\ConsoleRouter;
use Src\DI\Container;
use Src\DI\ContainerCompiler;
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
        $containerFile = $this->cacheDir . '/CachedContainer.php';
        $routesFile = $this->cacheDir . '/CompiledRoutes.php';
        $consoleRouter = $this->cacheDir . '/CompiledConsoleRoutes.php';

        return !file_exists($containerFile)
            || !file_exists($routesFile)
            || !file_exists($consoleRouter)
            || (getenv('APP_ENV') === 'dev');
    }

    public function compile(ApplicationBootstrap $bootstrap): void
    {
        $container = new Container();
        $container->singleton(Router::class);
        /** @var Router $router */
        $router = $container->get(Router::class);
        $container->singleton(ConsoleRouter::class);
        /** @var ConsoleRouter $consoleRouter */
        $consoleRouter = $container->get(ConsoleRouter::class);

        foreach ($bootstrap->modules as $module) {
            $module->registerRoutes($router);
            foreach ($router->getRoutes() as $route) {
                foreach ($route->middlewares as $middleware) {
                    $container->bind($middleware);
                }
            }
            $module->registerCommands($consoleRouter);
            $module->registerServices($container);
        }
        $containerCompiler = new ContainerCompiler($container);
        $containerCompiler->compile(
            outputFile: $this->cacheDir . '/CachedContainer.php',
            namespace: 'App\\cache\\compiled',
            router: $router,
            consoleRouter: $consoleRouter
        );

        $routeCompiler = new RouteCompiler($router);
        $routeCompiler->compile(
            outputFile: $this->cacheDir . '/CompiledRoutes.php',
            namespace: 'App\\cache\\compiled'
        );
        $consoleRouterCompiler = new ConsoleRouteCompiler($consoleRouter);
        $consoleRouterCompiler->compile(
            outputFile: $this->cacheDir . '/CompiledConsoleRoutes.php',
            namespace: 'App\\cache\\compiled'
        );
    }

    /**
     * @return array{container: ContainerInterface, router:Router, consoleRouter: ConsoleRouter}
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function loadCompiled(): array
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


        return ['container' => $container, 'router' => $router, 'consoleRouter' => $consoleRouter];
    }
}