<?php

namespace App\modules;

use App\AnotherLogger;
use App\bootstrap\ModuleManifest;
use App\Commands\ClearCacheCommand;
use App\Controllers\UserController;
use App\Logger;
use App\LoggerInterface;
use App\Middlewares\AddOneToOneMiddleware;
use App\Middlewares\AfterMiddleware;
use App\Middlewares\CorsMiddleware;
use App\UserRepository;
use Src\Console\ConsoleRoute;
use Src\Console\ConsoleRouter;
use Src\DI\Container;
use Src\Router\Route;
use Src\Router\RouteHandler;
use Src\Router\Router;

class AppModule implements ModuleManifest
{

    public function registerServices(Container $container): void
    {
        $container->singleton(UserRepository::class);
        $container->singleton(LoggerInterface::class, Logger::class);
        $container->when(UserRepository::class)
            ->needs(LoggerInterface::class)
            ->give(AnotherLogger::class);
    }

    public function registerRoutes(Router $router): void
    {
        $router
            ->addRoute(
                new Route(
                    'GET',
                    '/',
                    new RouteHandler(
                        UserController::class,
                        'index'
                    ),
                    []
                )
            )->addRoute(
                new Route(
                    'POST',
                    '/create/{id}/posts/{postId}',
                    new RouteHandler(
                        UserController::class,
                        'create'
                    ),
                    [AfterMiddleware::class, AddOneToOneMiddleware::class, CorsMiddleware::class]
                )
            );
    }

    public function registerCommands(ConsoleRouter $router): void
    {
        $router->addRoute(
            new ConsoleRoute(
                'cache:clear',
                new RouteHandler(
                    ClearCacheCommand::class,
                    'handle'
                ),
                []
            )
        );
    }
}