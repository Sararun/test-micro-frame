<?php

namespace Src\Console;

class ConsoleInput
{
    private(set) string $commandName = '';
    private(set) array $arguments = [];
    private(set) array $options = [];
    private(set) ConsoleOutput $output;

    public function __construct()
    {
        $argv = $_SERVER['argv'] ?? [];
        $this->output = new ConsoleOutput();
        $this->parse($argv);
    }

    private function parse(array $argv): void
    {
        array_shift($argv);

        if (empty($argv)) {
            $this->commandName = 'list';
            return;
        }

        $this->commandName = array_shift($argv);

        $argIndex = 0;
        for ($i = 0; $i < count($argv); $i++) {
            $token = $argv[$i];

            if (str_starts_with($token, '--')) {
                $this->parseOption($token, $argv, $i);
            } elseif (str_starts_with($token, '-')) {
                $this->parseShortOption($token, $argv, $i);
            } else {
                $this->arguments[$argIndex++] = $token;
            }
        }
    }

    private function parseOption(string $token, array $argv, int &$index): void
    {
        $token = substr($token, 2);

        if (str_contains($token, '=')) {
            [$name, $value] = explode('=', $token, 2);
            $this->options[$name] = $value;
        } else {
            $nextToken = $argv[$index + 1] ?? null;

            if ($nextToken !== null && !str_starts_with($nextToken, '-')) {
                $this->options[$token] = $nextToken;
                $index++;
            } else {
                $this->options[$token] = true;
            }
        }
    }

    private function parseShortOption(string $token, array $argv, int &$index): void
    {
        $token = substr($token, 1);

        if (strlen($token) > 1) {
            if (str_contains($token, '=')) {
                [$name, $value] = explode('=', $token, 2);
                $this->options[$name] = $value;
            } else {
                foreach (str_split($token) as $flag) {
                    $this->options[$flag] = true;
                }
            }
        } else {
            $nextToken = $argv[$index + 1] ?? null;

            if ($nextToken !== null && !str_starts_with($nextToken, '-')) {
                $this->options[$token] = $nextToken;
                $index++;
            } else {
                $this->options[$token] = true;
            }
        }
    }


    public function getCommandName(): string
    {
        return $this->commandName;
    }

    public function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function getOutput(): ConsoleOutput
    {
        return $this->output;
    }
}