<?php

declare(strict_types=1);

namespace Src\DI;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Src\DI\Interfaces\ToCompileContainer;

readonly class ContainerCompiler
{
    public function __construct(
        private ToCompileContainer $container,
    ) {
    }

    /**
     * @throws Exception
     */
    public function compile(string $outputFile, string $namespace, string $className = 'CachedContainer'): void
    {
        $this->validateBindings();

        $code = $this->generateContainerClass($className, $namespace);

        $directory = dirname($outputFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_put_contents($outputFile, $code) === false) {
            throw new Exception("Failed to write compiled container to '{$outputFile}'");
        }
        $this->container->compiled = true;
    }

    /**
     * @throws Exception
     */
    private function validateBindings(): void
    {
        foreach ($this->container->bindings as $abstract => $binding) {
            try {
                $concrete = $binding->concrete;

                if (is_string($concrete) && class_exists($concrete)) {
                    $reflector = new ReflectionClass($concrete);

                    if (!$reflector->isInstantiable()) {
                        throw new Exception("Class '{$concrete}' is not instantiable");
                    }

                    $constructor = $reflector->getConstructor();
                    if ($constructor) {
                        $this->validateMethodParameters($constructor, $abstract);
                    }
                }
            } catch (\Throwable $e) {
                throw new Exception("Validation failed for '{$abstract}': " . $e->getMessage());
            }
        }
    }

    /**
     * @throws Exception
     */
    private function validateMethodParameters(ReflectionMethod $method, string $context): void
    {
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                $hasContextual = isset($this->container->contextual[$context][$typeName]);

                if (!$hasContextual && !isset($this->container->bindings[$typeName]) && !class_exists($typeName)) {
                    if (!$param->isDefaultValueAvailable() && !$type->allowsNull()) {
                        throw new Exception(
                            "Cannot resolve parameter '\${$param->getName()}' of type '{$typeName}' in {$context}"
                        );
                    }
                }
            } elseif (!$param->isDefaultValueAvailable() && $type && !$type->allowsNull()) {
                throw new Exception(
                    "Cannot resolve parameter '\${$param->getName()}' (builtin type) in {$context}"
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    private function generateContainerClass(string $className, string $namespace): string
    {
        $methods = [];
        $getterCases = [];
        $generatedMethods = [];

        $classesToGenerate = array_map(function (BindingValue $binding) {
            return $binding;
        }, $this->container->bindings);

        foreach ($this->container->contextual as $dependencies) {
            foreach ($dependencies as $implementation) {
                if (is_string($implementation) && !isset($classesToGenerate[$implementation])) {
                    $classesToGenerate[$implementation] = new BindingValue($implementation, false);
                }
            }
        }

        foreach ($classesToGenerate as $abstract => $binding) {
            $methodName = $this->generateMethodName($abstract);

            if (isset($generatedMethods[$methodName])) {
                continue;
            }

            $generatedMethods[$methodName] = true;

            if (isset($this->container->bindings[$abstract])) {
                $getterCases[] = $this->generateGetterCase($abstract, $methodName, $binding->singleton);
            }

            $methods[] = $this->generateFactoryMethod($abstract, $binding, $methodName);
        }

        $getterCasesCode = implode("\n            ", $getterCases);
        $methodsCode = implode("\n\n    ", $methods);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Psr\Container\ContainerInterface;
use Src\DI\NotFoundException;

/**
 * Compiled container - auto-generated, do not edit!
 * Generated at: {$this->getCurrentDateTime()}
 */
class {$className} implements ContainerInterface
{
    private array \$instances = [];

    public function get(string \$id): mixed
    {
        return match(\$id) {
{$getterCasesCode}
            default => throw new NotFoundException("Entry '{\$id}' not found in container"),
        };
    }

    public function has(string \$id): bool
    {
        return match(\$id) {
{$this->generateHasCases()}
            default => false,
        };
    }

    {$methodsCode}
}

PHP;
    }

    private function generateMethodName(string $className): string
    {
        $name = str_replace(['\\', 'Interface'], '', $className);
        $name = str_replace(['/', '-', '_'], '', $name);
        return 'get' . $name;
    }

    private function escapeClassName(string $className): string
    {
        return '\\' . ltrim($className, '\\');
    }

    private function generateGetterCase(string $abstract, string $methodName, bool $isSingleton): string
    {
        $abstractEscaped = addslashes($abstract);

        if ($isSingleton) {
            return "'{$abstractEscaped}' => \$this->instances['{$abstractEscaped}'] ??= \$this->{$methodName}(),";
        } else {
            return "'{$abstractEscaped}' => \$this->{$methodName}(),";
        }
    }

    /**
     * @throws Exception
     */
    private function generateFactoryMethod(string $abstract, BindingValue $binding, string $methodName): string
    {
        $concrete = $binding->concrete;

        return $this->generateClassFactoryMethod($abstract, $concrete, $methodName, $binding->singleton);
    }

    /**
     * @throws Exception
     */
    private function generateClassFactoryMethod(
        string $abstract,
        string $concrete,
        string $methodName,
        bool $isSingleton
    ): string {
        try {
            $reflector = new ReflectionClass($concrete);
            $constructor = $reflector->getConstructor();

            $concreteEscaped = $this->escapeClassName($concrete);
            $cacheKey = addslashes($abstract);

            if ($isSingleton) {
                if ($constructor === null) {
                    return <<<PHP
private function {$methodName}(): object
    {
        return \$this->instances['{$cacheKey}'] ??= new {$concreteEscaped}();
    }
PHP;
                }

                $params = $constructor->getParameters();
                $dependencies = $this->generateDependenciesCode($params, $abstract);

                if (empty($dependencies)) {
                    return <<<PHP
private function {$methodName}(): object
    {
        return \$this->instances['{$cacheKey}'] ??= new {$concreteEscaped}();
    }
PHP;
                }

                $dependenciesCode = implode(",\n            ", $dependencies);

                return <<<PHP
private function {$methodName}(): object
    {
        return \$this->instances['{$cacheKey}'] ??= new {$concreteEscaped}(
            {$dependenciesCode}
        );
    }
PHP;
            }

            // Transient
            if ($constructor === null) {
                return <<<PHP
private function {$methodName}(): object
    {
        return new {$concreteEscaped}();
    }
PHP;
            }

            $params = $constructor->getParameters();
            $dependencies = $this->generateDependenciesCode($params, $abstract);

            if (empty($dependencies)) {
                return <<<PHP
private function {$methodName}(): object
    {
        return new {$concreteEscaped}();
    }
PHP;
            }

            $dependenciesCode = implode(",\n            ", $dependencies);

            return <<<PHP
private function {$methodName}(): object
    {
        return new {$concreteEscaped}(
            {$dependenciesCode}
        );
    }
PHP;
        } catch (ReflectionException $e) {
            throw new Exception("Failed to generate factory for '{$abstract}': " . $e->getMessage());
        }
    }

    /**
     * @param ReflectionParameter[] $parameters
     * @return string[]
     * @throws Exception
     */
    private function generateDependenciesCode(array $parameters, string $context): array
    {
        $code = [];

        foreach ($parameters as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if (isset($this->container->contextual[$context][$typeName])) {
                    $contextualConcrete = $this->container->contextual[$context][$typeName];

                    if (is_string($contextualConcrete)) {
                        $methodName = $this->generateMethodName($contextualConcrete);
                        $code[] = "\$this->{$methodName}()";
                    } else {
                        throw new \RuntimeException("Contextual closures are not supported in compiled container");
                    }
                } else {
                    $methodName = $this->generateMethodName($typeName);
                    $code[] = "\$this->{$methodName}()";
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $defaultValue = $param->getDefaultValue();
                $code[] = var_export($defaultValue, true);
            } else {
                throw new \RuntimeException("Cannot resolve parameter '\${$param->getName()}' in {$context}");
            }
        }

        return $code;
    }

    private function generateHasCases(): string
    {
        $cases = [];
        foreach (array_keys($this->container->bindings) as $abstract) {
            $abstractEscaped = addslashes($abstract);
            $cases[] = "'{$abstractEscaped}' => true,";
        }
        return implode("\n            ", $cases);
    }

    private function getCurrentDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }
}