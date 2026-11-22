<?php

namespace App\Classes;

use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

final class ClassesModel
{
    public function page(): array
    {
        $groups = require __DIR__ . '/reference.php';

        foreach ($groups as &$group) {
            $classes = [];
            foreach ($group['classes'] as $name => $entry) {
                $class = new ReflectionClass($group['namespace'] . '\\' . $name);
                $classes[] = $this->describe($class, $entry);
            }
            $group['classes'] = $classes;
        }
        unset($group);

        return ['title' => 'Class reference', 'groups' => $groups];
    }

    private function describe(ReflectionClass $class, array $entry): array
    {
        $methods = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            $description = $entry['methods'][$method->name] ?? null;
            if ($description === null) {
                throw new LogicException("Missing reference for {$class->name}::{$method->name}");
            }
            $methods[] = ['signature' => $this->methodSignature($method), 'description' => $description];
        }

        $properties = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) as $property) {
            if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            $description = $entry['properties'][$property->name] ?? null;
            if ($description === null) {
                throw new LogicException("Missing reference for {$class->name}::\${$property->name}");
            }
            $visibility = $property->isPublic() ? 'public' : 'protected';
            $static = $property->isStatic() ? ' static' : '';
            $readonly = $property->isReadOnly() ? ' readonly' : '';
            $properties[] = [
                'signature' => "{$visibility}{$static}{$readonly} {$property->getType()} \${$property->name}",
                'description' => $description,
            ];
        }

        $parent = $class->getParentClass();
        $modifier = $class->isAbstract() ? 'abstract ' : ($class->isFinal() ? 'final ' : '');
        return [
            'name' => $class->getName(),
            'shortName' => $class->getShortName(),
            'anchor' => '#' . $this->classId($class->getName()),
            'id' => $this->classId($class->getName()),
            'declaration' => $modifier . 'class ' . $class->getName(),
            'parent' => $parent ? $parent->getName() : '',
            'parentAnchor' => $parent && !$parent->isInternal() ? '#' . $this->classId($parent->getName()) : '',
            'summary' => $entry['summary'],
            'properties' => $properties,
            'methods' => $methods,
        ];
    }

    private function classId(string $name): string
    {
        return strtolower(str_replace('\\', '-', $name));
    }

    private function methodSignature(ReflectionMethod $method): string
    {
        $visibility = $method->isPublic() ? 'public' : 'protected';
        $static = $method->isStatic() ? ' static' : '';
        $parameters = implode(', ', array_map($this->parameterSignature(...), $method->getParameters()));
        $result = $method->hasReturnType() ? ': ' . $method->getReturnType() : '';

        return "{$visibility}{$static} function {$method->name}({$parameters}){$result}";
    }

    private function parameterSignature(ReflectionParameter $parameter): string
    {
        $type = $parameter->hasType() ? $parameter->getType() . ' ' : '';
        $reference = $parameter->isPassedByReference() ? '&' : '';
        $variadic = $parameter->isVariadic() ? '...' : '';
        $signature = "{$type}{$reference}{$variadic}\${$parameter->name}";
        if (!$parameter->isDefaultValueAvailable()) {
            return $signature;
        }

        $default = $parameter->getDefaultValue();
        $value = $parameter->isDefaultValueConstant()
            ? $parameter->getDefaultValueConstantName()
            : match ($default) {
                [] => '[]',
                null => 'null',
                default => var_export($default, true),
            };

        return $signature . ' = ' . $value;
    }
}
