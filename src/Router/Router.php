<?php
namespace Src\Router;

use Psr\Container\ContainerInterface;
use Src\DI\Container;
use Src\Router\Interfaces\MiddlewareInterface;

class Router
{
    private array $routes = [];

    public function addRoute(Route $route): self
    {
        $this->routes[] = $route;
        return $this;
    }

    public function dispatch(ContainerInterface $container, Request $request): mixed
    {
        $requestMethod = $request->getMethod();
        $requestUri = $request->getUri();

        foreach ($this->routes as $route) {
            $pattern = $this->convertPathToRegex($route->path);

            if ($route->method === $requestMethod && preg_match($pattern, $requestUri, $matches)) {
                $params = $this->extractParams($matches);
                $request->setParams($params);

                return $this->executeRoute($container, $route, $request, $params);
            }
        }

        throw new \RuntimeException('Route not found: ' . $requestUri);
    }

    private function executeRoute(
        ContainerInterface $container,
        Route $route,
        Request $request,
        array $params
    ): mixed {
        $runtimeParams = array_merge(['request' => $request], $params);

        $controller = function (Request $req) use ($container, $route, $runtimeParams) {
            $runtimeParams['request'] = $req;

            if (method_exists($container, 'callController')) {
                return $container->callController(
                    $route->handler->class,
                    $route->handler->method,
                    $runtimeParams
                );
            }

            return $container->call(
                [$route->handler->class, $route->handler->method],
                $runtimeParams
            );
        };

        // Собираем цепочку
        $middlewares = $route->middlewares;
        $pipeline = array_reduce(
            array_reverse($middlewares),
            fn(callable $next, string $middleware) => function (Request $req) use ($middleware, $next, $container) {
                $instance = $container->get($middleware);
                return $instance->handle($req, $next);
            },
            $controller
        );

        return $pipeline($request);
    }

    private function convertPathToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function extractParams(array $matches): array
    {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}