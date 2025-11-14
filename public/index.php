<?php

require_once __DIR__ . '/../vendor/autoload.php';


use App\bootstrap\ApplicationBootstrap;
use App\bootstrap\CompilationManager;
use App\modules\AppModule;
use Src\DI\Container;
use Src\DI\ContainerCompiler;
use Src\Router\Request;
use Src\Router\Response;
use Src\Router\Router;


try {
    $bootstrap = new ApplicationBootstrap();
    $modules = require_once __DIR__ . '/../app/modules_declaration.php';
    foreach ($modules as $module) {
        $bootstrap->registerModule(new $module);
    }

    $compilationManager = new CompilationManager(__DIR__ . '/../app/cache/compiled');
    if ($compilationManager->shouldCompile()) {
        $compilationManager->compile($bootstrap);
    }

    /** @var Router $router */
    $compiled =  $compilationManager->loadCompiled();
    $container = $compiled['container'];
    $router = $compiled['router'];
    $request = Request::create();

    $response = $router->dispatch($container, $request);
    if ($response instanceof Response) {
        $response->send();
    } else {
        (new Response())->json(['data' => $response])->send();
    }
} catch (Throwable $t) {
    new Response()->json(['error' => $t->getMessage()])->send();
}