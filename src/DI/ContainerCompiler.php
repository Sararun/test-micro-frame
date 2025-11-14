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
use Src\Console\ConsoleRouter;
use Src\DI\Interfaces\ToCompileContainer;
use Src\Router\Request;
use Src\Router\Router;

class ContainerCompiler
{
    private array $controllerMethods = [];

    private array $consoleMethods = [];

    public function __construct(
        private readonly ToCompileContainer $container,
    ) {
    }

    /**
     * @throws Exception
     */
    public function compile(
        string $outputFile,
        string $namespace,
        ?Router $router = null,
        ?ConsoleRouter $consoleRouter = null
    ): void {
        $this->validateBindings();

        if ($router !== null) {
            $this->analyzeControllers($router);
        }

        if ($consoleRouter !== null) {
            $this->analyzeCommands($consoleRouter);
        }

        $code = $this->generateContainerClass('CachedContainer', $namespace);
        $directory = dirname($outputFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        if (file_put_contents($outputFile, $code) === false) {
            throw new Exception("Failed to write compiled container to '{$outputFile}'");
        }

        $this->container->compiled = true;
    }

    private function analyzeCommands(ConsoleRouter $router): void
    {
        foreach ($router->getCommands() as $route) {
            $controllerClass = $route->handler->class;
            $methodName = $route->handler->method;

            $key = $controllerClass . '::' . $methodName;
            if (!isset($this->consoleMethods[$key])) {
                $this->consoleMethods[$key] = [
                    'class' => $controllerClass,
                    'method' => $methodName,
                    'route_path' => $route->path
                ];
            }

            if (!isset($this->container->bindings[$controllerClass])) {
                $this->container->bind($controllerClass, $controllerClass, false);
            }
        }
    }


    private function analyzeControllers(Router $router): void
    {
        foreach ($router->getRoutes() as $route) {
            $controllerClass = $route->handler->class;
            $methodName = $route->handler->method;

            $key = $controllerClass . '::' . $methodName;
            if (!isset($this->controllerMethods[$key])) {
                $this->controllerMethods[$key] = [
                    'class' => $controllerClass,
                    'method' => $methodName,
                    'route_path' => $route->path
                ];
            }

            if (!isset($this->container->bindings[$controllerClass])) {
                $this->container->bind($controllerClass, $controllerClass, false);
            }
        }
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

                if ($this->isRuntimeType($typeName)) {
                    continue;
                }

                $hasContextual = isset($this->container->contextual[$context][$typeName]);

                if (!$hasContextual && !isset($this->container->bindings[$typeName]) && !class_exists($typeName)) {
                    if (!$param->isDefaultValueAvailable() && !$type->allowsNull()) {
                        throw new Exception(
                            "Cannot resolve parameter '\${$param->getName()}' of type '{$typeName}' in {$context}"
                        );
                    }
                }
            } elseif (!$param->isDefaultValueAvailable() && $type && !$type->allowsNull()) {
                if (!$this->isControllerMethod($context)) {
                    throw new Exception(
                        "Cannot resolve parameter '\${$param->getName()}' (builtin type) in {$context}"
                    );
                }
            }
        }
    }

    private function isRuntimeType(string $typeName): bool
    {
        $typeName = ltrim($typeName, '\\');

        return in_array($typeName, [
            'Src\\Router\\Request',
            'Src\\Router\\Response',
            Request::class,
            'Src\\Console\\ConsoleInput',
            'Src\\Console\\ConsoleOutput',
        ], true);
    }

    private function isControllerMethod(string $context): bool
    {
        return array_any($this->controllerMethods, fn($methodInfo) => $methodInfo['class'] === $context);
    }

    /**
     * @throws Exception
     */
    private function generateContainerClass(string $className, string $namespace): string
    {
        $methods = [];
        $getterCases = [];
        $controllerMethodFactories = [];
        $consoleMethodFactories = [];
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

        foreach ($this->controllerMethods as $methodInfo) {
            $controllerMethodFactories[] = $this->generateControllerMethodInvoker($methodInfo);
        }

        foreach ($this->consoleMethods as $methodInfo) {
            $consoleMethodFactories[] = $this->generateConsoleMethodInvoker($methodInfo);
        }

        $getterCasesCode = implode("\n            ", $getterCases);
        $methodsCode = implode("\n\n    ", $methods);
        $controllerMethodsCode = implode("\n\n    ", $controllerMethodFactories);
        $consoleMethodsCode = implode("\n\n    ", $consoleMethodFactories);

        $callControllerMethod = $this->generateCallControllerMethod();
        $callCommandMethod = $this->generateCallCommandMethod();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Psr\Container\ContainerInterface;
use Src\DI\NotFoundException;
use Src\\router\Request;

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

    {$callControllerMethod}

    {$callCommandMethod}

    {$methodsCode}

    {$controllerMethodsCode}

    {$consoleMethodsCode}
}

PHP;
    }

    private function generateCallControllerMethod(): string
    {
        $cases = [];
        foreach ($this->controllerMethods as $key => $methodInfo) {
            $methodName = $this->generateControllerMethodName($methodInfo['class'], $methodInfo['method']);
            $cases[] = "            '{$key}' => \$this->{$methodName}(\$runtimeParams),";
        }

        $casesCode = implode("\n", $cases);

        return <<<PHP
/**
     * Вызывает метод контроллера с разрешенными зависимостями
     */
    public function callController(string \$controller, string \$method, array \$runtimeParams = []): mixed
    {
        \$key = \$controller . '::' . \$method;
        
        return match(\$key) {
{$casesCode}
            default => throw new NotFoundException("Controller method '{\$key}' not found"),
        };
    }
PHP;
    }

    private function generateCallCommandMethod(): string
    {
        $cases = [];
        foreach ($this->consoleMethods as $key => $methodInfo) {
            $methodName = $this->generateControllerMethodName($methodInfo['class'], $methodInfo['method']);
            $cases[] = "            '{$key}' => (int) \$this->{$methodName}(\$runtimeParams),";
        }

        $casesCode = implode("\n", $cases);

        return <<<PHP
/**
     * Вызывает консольную команду. Ожидается, что команды вернут int (код завершения).
     */
    public function callCommand(string \$controller, string \$method, array \$runtimeParams = []): int
    {
        \$key = \$controller . '::' . \$method;

        return match(\$key) {
{$casesCode}
            default => throw new NotFoundException("Console command '{\$key}' not found"),
        };
    }
PHP;
    }


    /**
     * @throws Exception
     */
    private function generateControllerMethodInvoker(array $methodInfo): string
    {
        $controllerClass = $methodInfo['class'];
        $method = $methodInfo['method'];
        $methodName = $this->generateControllerMethodName($controllerClass, $method);

        try {
            $reflector = new ReflectionMethod($controllerClass, $method);
            $params = $reflector->getParameters();

            $dependencies = [];
            foreach ($params as $param) {
                $paramCode = $this->generateControllerParameterCode($param);
                if ($paramCode !== null) {
                    $dependencies[] = $paramCode;
                }
            }

            $controllerFactory = $this->generateMethodName($controllerClass);
            $dependenciesCode = implode(",\n            ", $dependencies);

            if (empty($dependencies)) {
                return <<<PHP
private function {$methodName}(array \$runtimeParams): mixed
    {
        \$controller = \$this->{$controllerFactory}();
        return \$controller->{$method}();
    }
PHP;
            }

            return <<<PHP
private function {$methodName}(array \$runtimeParams): mixed
    {
        \$controller = \$this->{$controllerFactory}();
        return \$controller->{$method}(
            {$dependenciesCode}
        );
    }
PHP;
        } catch (ReflectionException $e) {
            throw new Exception("Failed to generate controller method invoker for '{$controllerClass}::{$method}': " . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function generateConsoleMethodInvoker(array $methodInfo): string
    {
        $controllerClass = $methodInfo['class'];
        $method = $methodInfo['method'];
        $methodName = $this->generateControllerMethodName($controllerClass, $method);

        try {
            $reflector = new ReflectionMethod($controllerClass, $method);
            $params = $reflector->getParameters();

            $dependencies = [];
            foreach ($params as $param) {
                $paramCode = $this->generateConsoleParameterCode($param);
                if ($paramCode !== null) {
                    $dependencies[] = $paramCode;
                }
            }

            $controllerFactory = $this->generateMethodName($controllerClass);
            $dependenciesCode = implode(",\n            ", $dependencies);

            if (empty($dependencies)) {
                return <<<PHP
private function {$methodName}(array \$runtimeParams): mixed
    {
        \$controller = \$this->{$controllerFactory}();
        return \$controller->{$method}();
    }
PHP;
            }

            return <<<PHP
private function {$methodName}(array \$runtimeParams): mixed
    {
        \$controller = \$this->{$controllerFactory}();
        return \$controller->{$method}(
            {$dependenciesCode}
        );
    }
PHP;
        } catch (ReflectionException $e) {
            throw new Exception("Failed to generate console command invoker for '{$controllerClass}::{$method}': " . $e->getMessage());
        }
    }

    private function generateControllerParameterCode(ReflectionParameter $param): ?string
    {
        $name = $param->getName();
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            if ($typeName === 'Src\\Router\\Request' || $typeName === Request::class) {
                return "(\$runtimeParams['request'] ?? throw new \\RuntimeException('Request not provided'))";
            }

            if ($type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $default = var_export($param->getDefaultValue(), true);
                    return "\$runtimeParams['{$name}'] ?? {$default}";
                }
                return "\$runtimeParams['{$name}'] ?? null";
            }

            if (!$this->isRuntimeType($typeName)) {
                $serviceFactory = $this->generateMethodName($typeName);
                return "\$this->{$serviceFactory}()";
            }
        }

        if ($param->isDefaultValueAvailable()) {
            $default = var_export($param->getDefaultValue(), true);
            return "\$runtimeParams['{$name}'] ?? {$default}";
        }

        return "\$runtimeParams['{$name}'] ?? null";
    }

    private function generateConsoleParameterCode(ReflectionParameter $param): ?string
    {
        $name = $param->getName();
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            if ($typeName === 'Src\\Console\\ConsoleInput') {
                return "(\$runtimeParams['input'] ?? throw new \\RuntimeException('ConsoleInput not provided'))";
            }

            if ($typeName === 'Src\\Console\\ConsoleOutput') {
                return "(\$runtimeParams['output'] ?? throw new \\RuntimeException('ConsoleOutput not provided'))";
            }

            if ($type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $default = var_export($param->getDefaultValue(), true);
                    return "(\$runtimeParams['arguments']['{$name}'] ?? \$runtimeParams['options']['{$name}'] ?? \$runtimeParams['{$name}'] ?? {$default})";
                }
                return "(\$runtimeParams['arguments']['{$name}'] ?? \$runtimeParams['options']['{$name}'] ?? \$runtimeParams['{$name}'] ?? null)";
            }

            if (!$this->isRuntimeType($typeName)) {
                $serviceFactory = $this->generateMethodName($typeName);
                return "\$this->{$serviceFactory}()";
            }
        }

        if ($param->isDefaultValueAvailable()) {
            $default = var_export($param->getDefaultValue(), true);
            return "(\$runtimeParams['arguments']['{$name}'] ?? \$runtimeParams['options']['{$name}'] ?? \$runtimeParams['{$name}'] ?? {$default})";
        }

        return "(\$runtimeParams['arguments']['{$name}'] ?? \$runtimeParams['options']['{$name}'] ?? \$runtimeParams['{$name}'] ?? null)";
    }

    private function generateControllerMethodName(string $class, string $method): string
    {
        $className = str_replace(['\\', '/', '-', '_'], '', $class);
        return 'call' . $className . ucfirst($method);
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

            if ($constructor === null) {
                if ($isSingleton) {
                    return <<<PHP
private function {$methodName}(): object
    {
        return \$this->instances['{$cacheKey}'] ??= new {$concreteEscaped}();
    }
PHP;
                }
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
                if ($isSingleton) {
                    return <<<PHP
private function {$methodName}(): object
    {
        return \$this->instances['{$cacheKey}'] ??= new {$concreteEscaped}();
    }
PHP;
                }
                return <<<PHP
private function {$methodName}(): object
    {
        return new {$concreteEscaped}();
    }
PHP;
            }

            $dependenciesCode = implode(",\n            ", $dependencies);

            if ($isSingleton) {
                return <<<PHP
private function {$methodName}(): object
    {
        return \$this->instances['{$cacheKey}'] ??= new {$concreteEscaped}(
            {$dependenciesCode}
        );
    }
PHP;
            }

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
                        throw new \Exception("Contextual closures are not supported in compiled container");
                    }
                } else {
                    $methodName = $this->generateMethodName($typeName);
                    $code[] = "\$this->{$methodName}()";
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $defaultValue = $param->getDefaultValue();
                $code[] = var_export($defaultValue, true);
            } else {
                throw new \Exception("Cannot resolve parameter '\${$param->getName()}' in {$context}");
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