<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\bootstrap\CompilationManager;
use Src\Console\ConsoleRouter;
use Src\Console\ConsoleInput;
use App\bootstrap\ApplicationBootstrap;

$bootstrap = new ApplicationBootstrap();
$modules = require_once __DIR__ . '/app/modules_declaration.php';
foreach ($modules as $module) {
    $bootstrap->registerModule(new $module);
}
$container = $bootstrap->init();
$compilationManager = new CompilationManager(__DIR__ . '/../app/cache/compiled');
if ($compilationManager->shouldCompile()) {
    $compilationManager->compile($container);
    $container = $compilationManager->loadCompiled();
}
$consoleRouter = $container->get(ConsoleRouter::class);
try {
    $input = new ConsoleInput();

    $exitCode = $consoleRouter->dispatch($container, $input);

    exit($exitCode);

} catch (Throwable $t) {
    fwrite(STDERR, "Error: " . $t->getMessage() . "\n");
    exit(1);
}