<?php

namespace App\Middlewares;

use App\LoggerInterface;
use Src\Router\Interfaces\MiddlewareInterface;
use Src\Router\Request;
use Src\Router\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new Response()
                ->setStatusCode(200)
                ->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, PUT, DELETE')
                ->setHeader('Access-Control-Allow-Headers', 'Content-Type');
        }

        $response = $next($request);
        return $response->setHeader('Access-Control-Allow-Origin', '*');
    }
}