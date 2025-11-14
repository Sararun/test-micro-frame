<?php

namespace Src\Router;

readonly class RouteHandler
{
    /**
     * @param class-string $class
     * @param string $method
     */
    public function __construct(private(set)string$class, private(set)string$method)
    {

    }
}