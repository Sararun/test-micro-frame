<?php

namespace Src\DI;

readonly class BindingValue
{
    /**
     * @param class-string|null $concrete
     * @param bool $singleton
     */
    public function __construct(public ?string $concrete, public bool $singleton) {}
}