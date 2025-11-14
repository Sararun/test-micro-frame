<?php

namespace Src\Console;

class ConsoleOutput
{
    public function write(string $message): self
    {
        echo $message;
        return $this;
    }

    public function writeln(string $message = ''): self
    {
        echo $message . PHP_EOL;
        return $this;
    }

    public function error(string $message): self
    {
        $this->writeln("\033[31mERROR: {$message}\033[0m");
        return $this;
    }

    public function success(string $message): self
    {
        $this->writeln("\033[32m{$message}\033[0m");
        return $this;
    }

    public function info(string $message): self
    {
        $this->writeln("\033[36m{$message}\033[0m");
        return $this;
    }

    public function table(array $headers, array $rows): self
    {
        $this->writeln(implode(' | ', $headers));
        $this->writeln(str_repeat('-', 50));

        foreach ($rows as $row) {
            $this->writeln(implode(' | ', $row));
        }

        return $this;
    }
}