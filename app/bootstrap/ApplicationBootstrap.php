<?php

namespace App\bootstrap;

class ApplicationBootstrap
{
    /**
     * @var ModuleManifest[]
     */
    private(set) array $modules = [];

    public function registerModule(ModuleManifest $manifest): self
    {
        $this->modules[] = $manifest;
        return $this;
    }
}