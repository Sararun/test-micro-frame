<?php

namespace Src\DI\Interfaces;

interface AllowContextualBinding
{
    public function addContextualBinding(string $concrete, string $abstract, string $implementation): void;
}