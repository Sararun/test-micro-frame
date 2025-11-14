<?php

namespace Src\Router;

readonly class RouteCompiler
{
    public function __construct(private Router $router)
    {
    }

    public function compile(string $outputFile, string $namespace, string $className = 'CompiledRoutes'): void
    {
        $routes = [];

        foreach ($this->router->getRoutes() as $route) {
            $routes[] = [
                'method' => $route->method,
                'path' => $route->path,
                'controller' => $route->handler->class,
                'action' => $route->handler->method,
                'middlewares' => $route->middlewares
            ];
        }

        $routesExport = var_export($routes, true);
        $dateTime = date('Y-m-d H:i:s');

        $code = '<?php

declare(strict_types=1);

namespace ' . $namespace . ';

use Src\Router\Router;
use Src\Router\Route;
use Src\Router\RouteHandler;

/**
 * Compiled routes - auto-generated, do not edit!
 * Generated at: ' . $dateTime . '
 */
class ' . $className . '
{
    private static array $routes = ' . $routesExport . ';
    
    public static function loadIntoRouter(Router $router): void
    {
        foreach (self::$routes as $routeData) {
            $router->addRoute(new Route(
                $routeData["method"],
                $routeData["path"],
                new RouteHandler($routeData["controller"], $routeData["action"]),
                $routeData["middlewares"]
            ));
        }
    }
    
    public static function getRoutes(): array
    {
        return self::$routes;
    }
}
';

        $directory = dirname($outputFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($outputFile, $code);
    }
}