<?php

declare(strict_types=1);

namespace App\cache\compiled;

use Psr\Container\ContainerInterface;
use Src\DI\NotFoundException;

/**
 * Compiled container - auto-generated, do not edit!
 * Generated at: 2025-11-12 21:31:53
 */
class CachedContainer implements ContainerInterface
{
    private array $instances = [];

    public function get(string $id): mixed
    {
        return match($id) {
'App\\UserRepository' => $this->instances['App\\UserRepository'] ??= $this->getAppUserRepository(),
            'App\\LoggerInterface' => $this->instances['App\\LoggerInterface'] ??= $this->getAppLogger(),
            default => throw new NotFoundException("Entry '{$id}' not found in container"),
        };
    }

    public function has(string $id): bool
    {
        return match($id) {
'App\\UserRepository' => true,
            'App\\LoggerInterface' => true,
            default => false,
        };
    }

    private function getAppUserRepository(): object
    {
        return $this->instances['App\\UserRepository'] ??= new \App\UserRepository(
            $this->getAppAnotherLogger()
        );
    }

    private function getAppLogger(): object
    {
        return $this->instances['App\\LoggerInterface'] ??= new \App\Logger();
    }

    private function getAppAnotherLogger(): object
    {
        return new \App\AnotherLogger(
            $this->getAppLogger()
        );
    }
}
