<?php

namespace Src\DI;

use Exception;
use Psr\Container\ContainerInterface;
use RuntimeException;

class ContextualBindingBuilder
{
    private ContainerInterface $container;
    private string $concrete;
    private ?string $needs = null;

    public function __construct(ContainerInterface $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    /**
     * Define the abstract dependency
     */
    public function needs(string $abstract): self
    {
        $this->needs = $abstract;
        return $this;
    }


    /**
     * @param class-string|string|callable $implementation
     * @throws RuntimeException
     */
    public function give(mixed $implementation): void
    {
        if ($this->needs === null) {
            throw new \RuntimeException("Must call needs() before give()");
        }

        $this->container->addContextualBinding(
            $this->concrete,
            $this->needs,
            $implementation
        );
    }
}