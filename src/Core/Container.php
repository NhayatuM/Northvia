<?php

declare(strict_types=1);

namespace Northvia\Core;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Dependency Injection Container implementation
 */
class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];

    /**
     * Bind a service to the container
     */
    public function bind(string $id, $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $id;
        }

        $this->bindings[$id] = compact('concrete', 'singleton');
        
        if ($singleton) {
            $this->singletons[$id] = true;
        }
    }

    /**
     * Bind a singleton service to the container
     */
    public function singleton(string $id, $concrete = null): void
    {
        $this->bind($id, $concrete, true);
    }

    /**
     * Bind an existing instance to the container
     */
    public function instance(string $id, $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Get a service from the container
     */
    public function get(string $id)
    {
        try {
            return $this->resolve($id);
        } catch (ReflectionException $e) {
            throw new ContainerNotFoundException("Service '{$id}' not found in container", 0, $e);
        }
    }

    /**
     * Check if container has a service
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Resolve a service from the container
     */
    private function resolve(string $id)
    {
        // Return existing instance if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if service is bound
        if (!isset($this->bindings[$id])) {
            // Try to auto-resolve the class
            if (class_exists($id)) {
                return $this->build($id);
            }
            
            throw new ContainerNotFoundException("Service '{$id}' not found in container");
        }

        $concrete = $this->bindings[$id]['concrete'];
        
        // Resolve the concrete implementation
        if ($concrete instanceof \Closure) {
            $object = $concrete($this);
        } elseif (is_string($concrete)) {
            $object = $this->build($concrete);
        } else {
            $object = $concrete;
        }

        // Cache singleton instances
        if (isset($this->singletons[$id])) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    /**
     * Build a class with dependency injection
     */
    private function build(string $className)
    {
        try {
            $reflection = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            throw new ContainerNotFoundException("Class '{$className}' not found", 0, $e);
        }

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class '{$className}' is not instantiable");
        }

        $constructor = $reflection->getConstructor();
        
        if ($constructor === null) {
            return new $className;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new RuntimeException(
                        "Cannot resolve parameter '{$parameter->getName()}' in '{$className}'"
                    );
                }
            } elseif ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();
                
                if ($type->isBuiltin()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new RuntimeException(
                            "Cannot resolve built-in parameter '{$parameter->getName()}' in '{$className}'"
                        );
                    }
                } else {
                    $dependencies[] = $this->resolve($typeName);
                }
            } else {
                throw new RuntimeException(
                    "Cannot resolve union type parameter '{$parameter->getName()}' in '{$className}'"
                );
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}

/**
 * Container exception for not found services
 */
class ContainerNotFoundException extends \Exception implements NotFoundExceptionInterface
{
}