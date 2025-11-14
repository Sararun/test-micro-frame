<?php

namespace App;

class UserRepository
{
    public function __construct(private readonly LoggerInterface $logger)
    {
//        $this->logger->log('Hellow User Repository');
    }
}