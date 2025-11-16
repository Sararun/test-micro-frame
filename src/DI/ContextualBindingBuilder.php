<?php

namespace Src\DI;

use Exception;
use Src\DI\Interfaces\AllowContextualBinding;

class ContextualBindingBuilder
{
    private ?string $needs = null;

    public function __construct(private AllowContextualBinding $container, private string $concrete)
    {

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
     * @param class-string $implementation
     * @throws Exception
     */
    public function give(string $implementation): void
    {
        if ($this->needs === null) {
            throw new \Exception("Must call needs() before give()");
        }

        $this->container->addContextualBinding(
            $this->concrete,
            $this->needs,
            $implementation
        );
    }
}