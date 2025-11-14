<?php

namespace Src\Console;

abstract class Command
{
    protected string $signature = '';
    protected string $description = '';

    abstract public function handle(ConsoleInput $input, ConsoleOutput $output): int;
}