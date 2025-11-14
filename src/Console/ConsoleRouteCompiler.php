<?php

namespace Src\Console;

readonly class ConsoleRouteCompiler
{

    public function __construct(private ConsoleRouter $router)
    {
    }

    public function compile(string $outputFile, string $namespace, string $className = 'CompiledConsoleRoutes'): void
    {
        $routes = [];
        foreach ($this->router->getCommands() as $route) {
            $routes[] = [
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

use Src\Console\ConsoleRouter;
use Src\Console\ConsoleRoute;
use Src\Router\RouteHandler;

/**
 * Compiled routes - auto-generated, do not edit!
 * Generated at: ' . $dateTime . '
 */
class ' . $className . '
{
    private static array $routes = ' . $routesExport . ';
    
    public static function loadIntoRouter(ConsoleRouter $router): void
    {
        foreach (self::$routes as $routeData) {
            $router->addRoute(new ConsoleRoute(
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