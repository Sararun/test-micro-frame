<?php

namespace Src\Console;

use Src\Router\RouteHandler;

readonly class ConsoleRoute
{
    /**
     * @param string $path
     * @param RouteHandler $handler
     * @param class-string[] $middlewares
     */
    public function __construct(
        private(set) string $path,
        private(set) RouteHandler $handler,
        private(set) array $middlewares
    ) {}
}