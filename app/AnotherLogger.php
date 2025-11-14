<?php

namespace App;

class AnotherLogger implements LoggerInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function log(string $message)
    {
        $this->logger->log("Another " . $message);
    }
}