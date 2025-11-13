<?php

namespace Src\DI\Interfaces;

use Src\DI\BindingValue;

interface ToCompileContainer
{
    /**
     * @var array<string, BindingValue>
     */
    public array $bindings {
        get;
    }

    /**
     * @var array<string, array<string, string|callable>>
     */
    public array $contextual {
        get;
    }
    public bool $compiled {
        set;
        get;
    }
}