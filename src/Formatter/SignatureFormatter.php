<?php

namespace TareqAS\Psym\Formatter;

use Psy\Formatter\ReflectorFormatter;
use TareqAS\Psym\Util\Helper;

class SignatureFormatter implements ReflectorFormatter
{
    public static function format(\Reflector $reflector): string
    {
        switch (true) {
            case $reflector instanceof \ReflectionFunction:
                return self::formatFunction($reflector);

            case $reflector instanceof \ReflectionMethod:
                return self::formatMethod($reflector);

            case $reflector instanceof \ReflectionProperty:
                return self::formatProperty($reflector);

            default:
                throw new \InvalidArgumentException('Unexpected Reflector class: '.\get_class($reflector));
        }
    }

    public static function formatFunction(\ReflectionFunction $function): string
    {
        $name = $function->getName();
        $parameters = self::getParametersSignature($function->getParameters());

        // function foo(...)
        return sprintf(
            '<keyword>function</keyword> <name>%s</name>(%s)',
            $name,
            implode(', ', $parameters)
        );
    }

    public static function formatMethod(\ReflectionMethod $method): string
    {
        $modifiers = implode(' ', \Reflection::getModifierNames($method->getModifiers()));
        $name = $method->getName();
        $parameters = self::getParametersSignature($method->getParameters());

        // public static function foo(...)
        return sprintf(
            '<keyword>%s function</keyword> <name>%s</name>(%s)',
            $modifiers,
            $name,
            implode(', ', $parameters)
        );
    }

    public static function formatProperty(\ReflectionProperty $property): string
    {
        $modifiers = implode(' ', \Reflection::getModifierNames($property->getModifiers()));
        $type = method_exists($property, 'hasType') && $property->hasType() ? self::formatReflectionType($property->getType()).' ' : '';
        $name = $property->getName();

        // private static ?int $count
        return sprintf(
            '<keyword>%s %s</keyword><name>$%s</name>',
            $modifiers,
            $type,
            $name
        );
    }

    private static function getParametersSignature(array $parameters): array
    {
        return array_map(function (\ReflectionParameter $param) {
            $type = $param->hasType() ? self::formatReflectionType($param->getType()).' ' : '';
            $isNullable = $param->hasType() && $param->getType()->allowsNull() ? '?' : '';
            $byReference = $param->isPassedByReference() ? '&' : '';
            $isVariadic = $param->isVariadic() ? '...' : '';
            $name = '$'.$param->getName();
            $default = '';

            if ($param->isDefaultValueAvailable()) {
                $defaultValue = $param->getDefaultValue();
                $default = ' = '.Helper::stringifyDefaultValue($defaultValue);
            }

            // ?int &$number = 0, ...$params
            return sprintf(
                '%s<keyword>%s</keyword>%s%s<name>%s</name>%s',
                $isNullable,
                $type,
                $byReference,
                $isVariadic,
                $name,
                $default
            );
        }, $parameters);
    }

    private static function formatReflectionType(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(function (\ReflectionNamedType $t) {
                return $t->getName();
            }, $type->getTypes()));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(function (\ReflectionNamedType $t) {
                return $t->getName();
            }, $type->getTypes()));
        }

        return (string) $type;
    }
}
