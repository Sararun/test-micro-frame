<?php

namespace App\Middlewares;

use Src\Router\Interfaces\MiddlewareInterface;
use Src\Router\Request;

class AddOneToOneMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        $request->onetoone = 1 + 1;
        return $next($request);
    }
}