<?php

namespace TareqAS\Psym\Parser;

use JetBrains\PHPStormStub\PhpStormStubsMap;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use TareqAS\Psym\Formatter\SignatureFormatter;

class Reflection
{
    private static $cache = [];
    private static $cacheFilesNodes = [];
    private $reflection = null;

    private function __construct($funcOrClassOrObject)
    {
        if (function_exists($funcOrClassOrObject)) {
            $this->reflection = new \ReflectionFunction($funcOrClassOrObject);
        }

        $className = is_object($funcOrClassOrObject) ? get_class($funcOrClassOrObject) : $funcOrClassOrObject;
        if (class_exists($className) || interface_exists($className)) {
            $this->reflection = new \ReflectionClass($className);
        }

        /*
         * it cannot proceed without function or class source file
         */
        if ($this->reflection && !$this->getFilePath()) {
            $this->reflection = null;
        }
    }

    public static function init($funcOrClassOrObject): self
    {
        return new self($funcOrClassOrObject);
    }

    public static function getFunction($functionName): array
    {
        return self::init($functionName)->getFunctionInfo();
    }

    public static function getClass($objectOrClass): array
    {
        return self::init($objectOrClass)->getClassInfo();
    }

    /**
     * Get merged class from type like, Foo|Bar, Foo&Bar.
     *
     * @param string $type union and intersection type
     *
     * @return array|array[]
     */
    public static function getClassFromType(string $type, ?string $name = null): array
    {
        if (false !== strpos($type, '|')) {
            $foundTypes = explode('|', $type);
        } elseif (false !== strpos($type, '&')) {
            $foundTypes = explode('&', $type);
        } else {
            $foundTypes = [$type];
        }

        $classes = [];
        foreach ($foundTypes as $foundType) {
            if (!$class = self::getClass($foundType)) {
                continue;
            }

            if (!$classes) {
                $classes = $class;
            } elseif (false !== strpos($type, '|')) {
                $classes = self::getUnionArray($classes, $class);
            } elseif (false !== strpos($type, '&')) {
                $classes = self::getIntersectedArray($classes, $class);
            } else {
                $classes = $class;
            }
        }

        if (!$name || !$classes) {
            return $classes;
        }

        foreach (['staticProperties', 'properties', 'staticMethods', 'methods'] as $identifier) {
            foreach ($classes[$identifier] as $value) {
                if ($value['name'] === $name) {
                    $value['identifier'] = in_array($identifier, ['staticMethods', 'methods']) ? 'method' : 'prop';

                    return $value;
                }
            }
        }

        return [];
    }

    public static function getClassItemFromType(string $type, string $name): array
    {
        if (!$type || !$name) {
            return [];
        }

        if ($class = self::getClassFromType($type)) {
            foreach (['staticProperties', 'properties', 'staticMethods', 'methods'] as $identifier) {
                foreach ($class[$identifier] as $value) {
                    if ($value['name'] === $name) {
                        $value['identifier'] = in_array($identifier, ['staticMethods', 'methods']) ? 'method' : 'property';

                        return $value;
                    }
                }
            }
        }

        return [];
    }

    private function getFunctionInfo(): array
    {
        if (!$this->reflection instanceof \ReflectionFunction) {
            return [];
        }

        $functionName = $this->reflection->getName();
        if (isset(self::$cache[$functionName])) {
            return self::$cache[$functionName];
        }

        $info = [
            'name' => $functionName,
            'doc' => DocParser::parse($doc = $this->getDoc($this->reflection))->removeTag('@source')->print(),
            'signature' => SignatureFormatter::format($this->reflection),
            'type' => $this->getType($this->reflection->getReturnType(), $doc, $this->reflection),
        ];

        return self::$cache[$functionName] = $info;
    }

    private function getClassInfo(): array
    {
        if (!$this->reflection instanceof \ReflectionClass) {
            return [];
        }

        $className = $this->reflection->getName();
        if (isset(self::$cache[$className])) {
            return self::$cache[$className];
        }

        $classInfo = [
            'staticProperties' => [], 'properties' => [],
            'staticMethods' => [], 'methods' => [],
        ];

        $properties = $this->reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $methods = $this->reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($properties as $property) {
            $info = [];
            $info['class'] = $className;
            $info['name'] = $property->getName();
            $info['doc'] = DocParser::parse($doc = $this->getDoc($property))->removeTag('@source')->print();
            $info['signature'] = SignatureFormatter::format($property);
            $info['type'] = $this->getType(method_exists($property, 'getType') ? $property->getType() : '', $doc, $property);

            $property->isStatic() ? $classInfo['staticProperties'][] = $info : $classInfo['properties'][] = $info;
        }

        foreach ($methods as $method) {
            $info = [];
            $info['class'] = $className;
            $info['name'] = $method->getName();
            $info['doc'] = DocParser::parse($doc = $this->getDoc($method))->removeTag('@source')->print();
            $info['signature'] = SignatureFormatter::format($method);
            $info['type'] = $this->getType($method->getReturnType(), $doc, $method);

            $method->isStatic() ? $classInfo['staticMethods'][] = $info : $classInfo['methods'][] = $info;
        }

        return self::$cache[$className] = $classInfo;
    }

    /**
     * @param \ReflectionFunction|\ReflectionProperty|\ReflectionMethod $reflection
     */
    private function getDoc($reflection, $sourceClass = ''): string
    {
        $name = $reflection->getName();

        if (!$doc = $reflection->getDocComment() ?: '') {
            $filePath = method_exists($reflection, 'getFileName') ? $reflection->getFileName() : false;
            $filePath = $filePath ?: $this->getFilePath();
            $doc = $this->getNodes($filePath)['docComments'][$name] ?? '';
        }

        if ($reflection instanceof \ReflectionFunction) {
            return $this->trimDoc($doc);
        }

        $classReflection = $reflection->getDeclaringClass();
        $parent = $classReflection->getParentClass() ? [$classReflection->getParentClass()] : [];
        $parents = array_merge($parent, $classReflection->getInterfaces());

        if (!$parents || ($doc && 1 !== preg_match('/@inheritdoc\b/i', $doc))) {
            if ($sourceClass) {
                $doc = DocParser::parse($doc)->addTag('@source', $sourceClass)->print();
            }

            return $this->trimDoc($doc);
        }

        foreach ($parents as $parent) {
            if ($this->doesThisClassHaveMethod($parent, $name)) {
                return $this->getDoc($parent->getMethod($name), $parent->getName());
            }

            if ($this->doesThisClassHaveProperty($parent, $name)) {
                return $this->getDoc($parent->getProperty($name), $parent->getName());
            }

            $grandParent = $parent->getParentClass() ? [$parent->getParentClass()] : [];
            $grandParents = array_merge($grandParent, $parent->getInterfaces());

            foreach ($grandParents as $grandParent) {
                if ($parent->hasMethod($name) && $grandParent->hasMethod($name)) {
                    return $this->getDoc($grandParent->getMethod($name), $grandParent->getName());
                }

                if ($parent->hasProperty($name) && $grandParent->hasProperty($name)) {
                    return $this->getDoc($grandParent->getProperty($name), $grandParent->getName());
                }
            }
        }

        return $this->trimDoc($doc);
    }

    private function doesThisClassHaveMethod($classOrInterface, $name): bool
    {
        if ($classOrInterface->hasMethod($name)) {
            $method = $classOrInterface->getMethod($name);

            return $method->getDeclaringClass()->getName() === $classOrInterface->getName();
        }

        return false;
    }

    private function doesThisClassHaveProperty($classOrInterface, $name): bool
    {
        if ($classOrInterface->hasProperty($name)) {
            $method = $classOrInterface->getProperty($name);

            return $method->getDeclaringClass()->getName() === $classOrInterface->getName();
        }

        return false;
    }

    private function trimDoc(string $doc): string
    {
        return preg_replace('/\n\s+\*/', "\n *", trim($doc));
    }

    /**
     * @param $reflection \ReflectionFunction|\ReflectionMethod|\ReflectionProperty
     */
    private function getType($types, $doc, $reflection): string
    {
        if (!$types && !$doc) {
            return '';
        }

        if ($types) {
            $separator = $types instanceof \ReflectionUnionType ? '|' : ($types instanceof \ReflectionIntersectionType ? '&' : ' ');
            if (in_array($separator, ['|', '&'])) {
                $foundTypes = $types->getTypes();
            } elseif ($types instanceof \ReflectionNamedType) {
                $foundTypes = [$types];
            } else {
                return '';
            }
            $foundTypes = array_map(function ($type) {
                return $this->getFullClassName($type->getName());
            }, $foundTypes);

            return implode($separator, $foundTypes);
        }

        $parser = DocParser::parse($doc);
        $types = $reflection instanceof \ReflectionProperty ? $parser->getPropertyType() : $parser->getReturnType();

        if ($types) {
            $separator = $types instanceof UnionTypeNode ? '|' : ($types instanceof IntersectionTypeNode ? '&' : ' ');
            $types = preg_replace('/\(|\)|(<[^>]+>)/', '', (string) $types);
            $foundTypes = explode($separator, $types);
            $source = $parser->getValue('@source');
            $foundTypes = array_map(function ($type) use ($source) {
                return $this->getFullClassName($type, $source);
            }, $foundTypes);

            return implode($separator, $foundTypes);
        }

        return '';
    }

    private function getNodesByClass(string $className): array
    {
        if (!class_exists($className) && !interface_exists($className)) {
            return [];
        }
        $reflection = new \ReflectionClass($className);

        return $this->getNodes($reflection->getFileName());
    }

    private function getNodes(?string $filePath = null): array
    {
        $filePath = $filePath ?: $this->getFilePath();

        if ($nodes = self::$cacheFilesNodes[$filePath] ?? []) {
            return $nodes;
        }

        $code = file_get_contents($filePath);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $nodeVisitor = new NodeVisitor();
        $traverser->addVisitor($nodeVisitor);
        $traverser->traverse($ast);

        $nodes = [
            'namespace' => $nodeVisitor->namespace,
            'className' => $nodeVisitor->className,
            'usedClasses' => $nodeVisitor->usedClasses,
            'docComments' => $nodeVisitor->docComments,
        ];

        return self::$cacheFilesNodes[$filePath] = $nodes;
    }

    private function getFilePath(): string
    {
        if ($path = $this->reflection->getFileName()) {
            return $path;
        }

        $name = $this->reflection->getName();
        $dir = PhpStormStubsMap::DIR;
        $functions = PhpStormStubsMap::FUNCTIONS;
        $classes = PhpStormStubsMap::CLASSES;
        $path = $classes[$name] ?? $functions[$name] ?? '';

        if (file_exists($path = $dir.'/'.$path)) {
            return $path;
        }

        return '';
    }

    private function getFullClassName(string $class, ?string $source = null)
    {
        $class = trim($class);
        $nodes = $source ? $this->getNodesByClass($source) : $this->getNodes();

        if (!$nodes) {
            return $class;
        }

        if (in_array($class, ['this', '$this', 'self', 'static'])) {
            return $nodes['className'] ?? $class;
        }

        $filter = array_filter($nodes['usedClasses'], function ($usedClass) use ($class) {
            return substr($usedClass, -strlen($class)) === $class;
        });

        $className = $nodes['namespace'] ? $nodes['namespace'].'\\'.$class : $class;
        if (!$filter && (class_exists($className) || interface_exists($className))) {
            $class = $className;
        }

        return reset($filter) ?: $class;
    }

    private static function getUnionArray(array $arrayA, array $arrayB): array
    {
        $merged = array_merge_recursive($arrayA, $arrayB);

        foreach ($merged as $key => $valueArr) {
            $tmpArr = array_unique(array_column($valueArr, 'name'));
            $merged[$key] = array_intersect_key($valueArr, $tmpArr);
        }

        return $merged;
    }

    private static function getIntersectedArray(array $arrayA, array $arrayB): array
    {
        $intersected = [];
        foreach (array_keys($arrayA) as $key) {
            $intersected[$key] = [];
            foreach ($arrayA[$key] as $a) {
                foreach ($arrayB[$key] as $b) {
                    if ($a['name'] === $b['name']) {
                        $intersected[$key][] = $a;
                    }
                }
            }
        }

        return $intersected;
    }
}
