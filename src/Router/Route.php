<?php

namespace Src\Router;

readonly class Route
{
    /**
     * @param string $method
     * @param string $path
     * @param RouteHandler $handler
     * @param class-string[] $middlewares
     */
    public function __construct(
        private(set) string $method,
        private(set) string $path,
        private(set) RouteHandler $handler,
        private(set) array $middlewares
    ) {}
}