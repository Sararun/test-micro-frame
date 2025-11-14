<?php

namespace App;

class Logger implements LoggerInterface
{

    public function log(string $message): void
    {
        echo $message;
    }
}