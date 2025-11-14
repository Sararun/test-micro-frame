<?php

namespace App\Commands;

use App\UserRepository;
use Src\Console\Command;
use Src\Console\ConsoleInput;
use Src\Console\ConsoleOutput;

class ClearCacheCommand extends Command
{
    protected string $signature = 'cache:clear {--compiled} {--routes} {--consoleRoutes} {--all}';
    protected string $description = 'Clear application cache';

    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir =  dirname(__DIR__, 1) . '/cache/compiled';
    }

    public function handle(ConsoleInput $input, ConsoleOutput $output): int
    {
        $clearAll = $input->hasOption('all');
        $clearCompiled = $input->hasOption('compiled') || $clearAll;
        $clearRoutes = $input->hasOption('routes') || $clearAll;
        $clearConsoleRoutes = $input->hasOption('consoleRoutes') || $clearAll;

        if (!$clearCompiled && !$clearRoutes) {
            $clearAll = true;
            $clearCompiled = true;
            $clearRoutes = true;
        }

        $output->info('Clearing cache...');
        $output->writeln();

        if ($clearAll) {
            $this->clearCompiledContainer($output);

            $this->clearCompiledRoutes($output);

            $this->clearCompiledConsoleRoutes($output);
            return 0;
        }

        if ($clearCompiled) {
            $this->clearCompiledContainer($output);
        }

        if ($clearRoutes) {
            $this->clearCompiledRoutes($output);
        }

        if ($clearConsoleRoutes) {
            $this->clearCompiledConsoleRoutes($output);
        }


        $output->writeln();

        $output->success('Cache cleared successfully!');
        return 0;
    }

    private function clearCompiledContainer(ConsoleOutput $output): bool
    {
        $containerFile = $this->cacheDir . '/CachedContainer.php';
        if (!file_exists($containerFile)) {
            $output->writeln('  ✓ Compiled container already clear');
            return true;
        }
        if (unlink($containerFile)) {
            $output->writeln('  ✓ Compiled container cleared');
            return true;
        } else {
            $output->error('  ✗ Failed to clear compiled container');
            return false;
        }
    }

    private function clearCompiledRoutes(ConsoleOutput $output): bool
    {
        $routesFile = $this->cacheDir . '/CompiledRoutes.php';

        if (!file_exists($routesFile)) {
            $output->writeln('  ✓ Compiled routes already clear');
            return true;
        }

        if (unlink($routesFile)) {
            $output->writeln('  ✓ Compiled routes cleared');
            return true;
        } else {
            $output->error('  ✗ Failed to clear compiled routes');
            return false;
        }
    }

    private function clearCompiledConsoleRoutes(ConsoleOutput $output): bool
    {
        $routesFile = $this->cacheDir . '/CompiledConsoleRoutes.php';

        if (!file_exists($routesFile)) {
            $output->writeln('  ✓ Compiled routes already clear');
            return true;
        }

        if (unlink($routesFile)) {
            $output->writeln('  ✓ Compiled routes cleared');
            return true;
        } else {
            $output->error('  ✗ Failed to clear compiled routes');
            return false;
        }
    }
}