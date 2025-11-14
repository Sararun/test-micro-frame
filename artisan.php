<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\bootstrap\CompilationManager;
use Src\Console\ConsoleRouter;
use Src\Console\ConsoleInput;
use App\bootstrap\ApplicationBootstrap;
use App\modules\AppModule;

try {
    $bootstrap = new ApplicationBootstrap();
    $modules = require_once __DIR__ . '/app/modules_declaration.php';
    foreach ($modules as $module) {
        $bootstrap->registerModule(new $module);
    }

    $compilationManager = new CompilationManager(__DIR__ . '/app/cache/compiled');
    if ($compilationManager->shouldCompile()) {
        $compilationManager->compile($bootstrap);
    }
    /** @var ConsoleRouter $router */
    $compiled =  $compilationManager->loadCompiled();
    $consoleRouter = $compiled['consoleRouter'];
    $container = $compiled['container'];

    $input = new ConsoleInput();

    $exitCode = $consoleRouter->dispatch($container, $input);

    exit($exitCode);

} catch (Throwable $t) {
    fwrite(STDERR, "Error: " . $t->getMessage() . "\n");
    exit(1);
}