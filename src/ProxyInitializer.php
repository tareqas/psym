<?php

namespace TareqAS\Psym;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Proxy\Proxy;

class ProxyInitializer
{
    private $traversing = [];
    private $traversed = [];

    private function __construct()
    {
    }

    public static function init($records): void
    {
        $init = new self();
        $init->initialize($records);
    }

    private function initialize($records): void
    {
        $records = is_array($records) ? $records : [$records];
        foreach ($records as $record) {
            $this->initializeEntity($record);
            $this->traversing = [];
            $this->traversed = [];
        }
    }

    private function initializeEntity($entity): void
    {
        $class = str_replace('Proxies\__CG__\\', '', get_class($entity));
        if (isset($this->traversing[$class]) || isset($this->traversed[$class])) {
            return;
        }
        $this->traversing[$class] = true;
        foreach ($this->getAllProperties($entity) as $property => $value) {
            if ($value instanceof Proxy && !$value->__isInitialized()) {
                $value->__load();
                $this->initializeEntity($value);
            } else if ($value instanceof Collection && !$value->isInitialized()) {
                $value->initialize();
                if ((($collection = $value->toArray()) && \count($collection) > 2) || !$collection) {
                    continue;
                }
                foreach ($collection as $coll) {
                    $this->initializeEntity($coll);
                }
            } else if (is_object($value) && in_array(get_class($value), Helper::getEntities())) {
                $this->initializeEntity($value);
            }
        }
        $this->traversed[$class] = true;
        unset($this->traversing[$class]);
    }

    private function getAllProperties($object): array
    {
        $properties = [];
        $reflectionClass = new \ReflectionClass($object);
        do {
            $currentProperties = $reflectionClass->getProperties();
            foreach ($currentProperties as $property) {
                $property->setAccessible(true);
                try {
                    $properties[$property->getName()] = $property->getValue($object);
                } catch (\Throwable $e) {
                    // to avoid, typed property must not be accessed before initialization
                }
            }
            $reflectionClass = $reflectionClass->getParentClass();
        } while ($reflectionClass);

        return $properties;
    }
}
