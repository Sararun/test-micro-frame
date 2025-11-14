<?php

namespace App\Middlewares;

use Src\Router\Interfaces\MiddlewareInterface;
use Src\Router\Request;

class AfterMiddleware implements MiddlewareInterface
{

    public function handle(Request $request, callable $next): mixed
    {
        $response = $next($request);
        return $response->setHeader('Final', 'hello');
    }
}