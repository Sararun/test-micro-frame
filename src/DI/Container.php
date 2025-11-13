<?php

declare(strict_types=1);

namespace Src\DI;

use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

use RuntimeException;
use Src\DI\Interfaces\ToCompileContainer;

class Container implements ContainerInterface, ToCompileContainer
{
    /**
     * @var array<string, BindingValue>
     */
    private(set) array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    public array $instances = [];

    /**
     * @var array<string, array<string, string|callable>>
     */
    private(set) array $contextual = [];

    /**
     * @var array<int, string>
     */
    private array $resolving = [];

    public bool $compiled = false;

    private bool $allowRuntimeResolution = true;

    /**
     * @param string $abstract
     * @param class-string|null $concrete
     * @param bool $singleton
     * @param mixed[] $parameters
     * @return void
     */
    public function bind(string $abstract, ?string $concrete = null, bool $singleton = false, array $parameters = []): void
    {
        if ($this->compiled) {
            throw new RuntimeException("Cannot bind after container is compiled");
        }

        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = new BindingValue($concrete, $singleton);
    }

    /**
     * @param string $abstract
     * @param class-string |null $concrete
     * @return void
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * @throws RuntimeException
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        if ($this->compiled) {
            throw new RuntimeException("Cannot add contextual bindings after container is compiled");
        }
        return new ContextualBindingBuilder($this, $concrete);
    }

    /**
     * @throws NotFoundException
     * @throws RuntimeException
     * @throws Exception
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new NotFoundException("Entry '{$id}' not found in container");
        }

        if ($this->allowRuntimeResolution || $this->compiled) {
            return $this->resolve($id);
        }

        throw new RuntimeException("Container must be compiled before use. Call compile() first.");
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;

            if (is_string($class)) {
                $class = $this->resolve($class);
            }

            $reflector = new ReflectionMethod($class, $method);
            $dependencies = $this->resolveMethodDependencies($reflector, $parameters);

            return $reflector->invokeArgs($class, $dependencies);
        }

        $reflector = new ReflectionFunction($callback);
        $dependencies = $this->resolveFunctionDependencies($reflector, $parameters);

        return $reflector->invokeArgs($dependencies);
    }

    /**
     * @throws Exception
     */
    private function resolve(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        $instance = $this->build($concrete, $parameters);

        if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]->singleton) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    private function getConcrete(string $abstract): mixed
    {
        if (!empty($this->resolving)) {
            $parent = end($this->resolving);
            if (isset($this->contextual[$parent][$abstract])) {
                return $this->contextual[$parent][$abstract];
            }
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]->concrete;
        }

        return $abstract;
    }

    /**
     * @throws RuntimeException
     * @throws ReflectionException
     */
    private function build(string $concrete, array $parameters = []): object
    {
        if (in_array($concrete, $this->resolving)) {
            throw new RuntimeException(
                "Circular dependency detected: " . implode(' -> ', $this->resolving) . " -> {$concrete}"
            );
        }

        $this->resolving[] = $concrete;

        try {
            $reflector = new ReflectionClass($concrete);

            if (!$reflector->isInstantiable()) {
                throw new RuntimeException("Class {$concrete} is not instantiable");
            }

            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                array_pop($this->resolving);
                return new $concrete();
            }

            $dependencies = $this->resolveMethodDependencies($constructor, $parameters);
            $instance = $reflector->newInstanceArgs($dependencies);

            array_pop($this->resolving);

            return $instance;
        } catch (Exception $e) {
            array_pop($this->resolving);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    private function resolveMethodDependencies(ReflectionMethod $method, array $parameters = []): array
    {
        return array_map(function (ReflectionParameter $param) use ($parameters, $method) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                return $parameters[$name];
            }

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $this->resolve($type->getName());
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new Exception("Cannot resolve parameter \${$name} in method {$method->getName()}");
        }, $method->getParameters());
    }

    /**
     * @throws Exception
     */
    private function resolveFunctionDependencies(ReflectionFunction $function, array $parameters = []): array
    {
        return array_map(function (ReflectionParameter $param) use ($parameters) {
            $name = $param->getName();

            if (array_key_exists($name, $parameters)) {
                return $parameters[$name];
            }

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $this->resolve($type->getName());
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new Exception("Cannot resolve parameter \${$name}");
        }, $function->getParameters());
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function setAllowRuntimeResolution(bool $allow): void
    {
        $this->allowRuntimeResolution = $allow;
    }

    public function addContextualBinding(string $concrete, string $abstract, mixed $implementation): void
    {
        if (!isset($this->bindings[$concrete])) {
            throw new RuntimeException(
                "Cannot add contextual binding: concrete '{$concrete}' is not registered and does not exist"
            );
        }
        if (!isset($this->bindings[$abstract])) {
            throw new RuntimeException(
                "Cannot add contextual binding: abstract '{$abstract}' is not registered and does not exist"
            );
        }
        $this->contextual[$concrete][$abstract] = $implementation;
    }
}