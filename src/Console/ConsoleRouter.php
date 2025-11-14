<?php

namespace Src\Console;

use Psr\Container\ContainerInterface;

class ConsoleRouter
{
    /**
     * @var array<string, ConsoleRoute>
     */
    private array $commands = [];

    public function addRoute(ConsoleRoute $route): self
    {
        $this->commands[$route->path] = $route;
        return $this;
    }

    public function dispatch(ContainerInterface $container, ConsoleInput $input): int
    {
        $commandName = $input->getCommandName();

        if ($commandName === '' || $commandName === 'list') {
            return $this->showList($input->getOutput());
        }

        if (!isset($this->commands[$commandName])) {
            $input->getOutput()->error("Command '{$commandName}' not found");
            return 1;
        }

        $route = $this->commands[$commandName];
        $handler = $route->handler;

        $class = $handler->class;
        $method = $handler->method;

        if (method_exists($container, 'callCommand')) {
            return $container->callCommand(
                $class,
                $method,
                [
                    'input' => $input,
                    'output' => $input->getOutput(),
                    'arguments' => $input->arguments ?? [],
                    'options' => $input->options ?? [],
                ]
            );
        }

        return $container->call(
            [$class, $method],
            [
                'input' => $input,
                'output' => $input->getOutput(),
                'arguments' => $input->arguments ?? [],
                'options' => $input->options ?? [],
            ]
        );
    }

    private function showList(ConsoleOutput $output): int
    {
        $output->writeln('Available commands:');
        $output->writeln('');

        foreach ($this->commands as $name => $route) {
            $output->writeln("  {$name}");
        }

        return 0;
    }


    public function getCommands(): array
    {
        return $this->commands;
    }
}