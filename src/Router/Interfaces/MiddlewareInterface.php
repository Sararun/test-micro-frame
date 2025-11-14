<?php

namespace Src\Router\Interfaces;

use Closure;
use Src\Router\Request;

interface MiddlewareInterface
{
    /**
     * @template TReturn
     * @param Request $request
     * @param callable(Request): TReturn $next
     * @return TReturn
     */
    public function handle(Request $request, callable $next): mixed;
}