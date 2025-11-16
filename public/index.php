<?php

require_once __DIR__ . '/../vendor/autoload.php';


use App\bootstrap\ApplicationBootstrap;
use App\bootstrap\CompilationManager;
use Src\Router\Request;
use Src\Router\Response;
use Src\Router\Router;


try {
    $bootstrap = new ApplicationBootstrap();
    $modules = require_once __DIR__ . '/../app/modules_declaration.php';
    foreach ($modules as $module) {
        $bootstrap->registerModule(new $module);
    }
    $container = $bootstrap->init();
    $compilationManager = new CompilationManager(__DIR__ . '/../app/cache/compiled');
    if ($compilationManager->shouldCompile()) {
        $compilationManager->compile($container);
        $container = $compilationManager->loadCompiled();
    }
    $router = $container->get(Router::class);
    $request = Request::create();

    $response = $router->dispatch($container, $request);
    if ($response instanceof Response) {
        $response->send();
    } else {
        (new Response())->json(['data' => $response])->send();
    }
} catch (Throwable $t) {
    new Response()->json(['data' => ['error' => $t->getMessage()]])->send();
}