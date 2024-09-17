<?php

namespace TareqAS\Psym\Util;

use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Proxy;

class DoctrineProxy
{
    private $nestedLevel;
    private $collectionSize;
    private $entities;

    private function __construct($nestedLevel, $collectionSize)
    {
        $this->nestedLevel = $nestedLevel;
        $this->collectionSize = $collectionSize;
        $this->entities = DoctrineEM::getEntities();
    }

    public static function init($vars, $nestedLevel, $collectionSize): void
    {
        $init = new self($nestedLevel, $collectionSize);
        $init->initialize($vars);
    }

    private function initialize($vars): void
    {
        if (is_array($vars)) {
            foreach ($vars as $var) {
                $this->initialize($var);
            }
        }

        if (is_object($vars) && $class = get_class($vars)) {
            $class = str_replace('Proxies\__CG__\\', '', $class);
        }

        if (isset($class) && in_array($class, $this->entities)) {
            $this->initializeEntity($vars, 0);
        }
    }

    private function initializeEntity($entity, $level): void
    {
        if (-1 !== $this->nestedLevel && $level > $this->nestedLevel) {
            return;
        }

        foreach ($this->getAllProperties($entity) as $value) {
            if ($value instanceof Proxy && !$value->__isInitialized()) {
                $value->__load();
                $this->initializeEntity($value, $level + 1);
            } elseif ($value instanceof Collection && !$value->isInitialized()) {
                $this->setExtraLazy($value);
                $collection = $value->slice(0, -1 === $this->collectionSize ? null : $this->collectionSize);
                $value->setInitialized(true);

                if (!$collection) {
                    continue;
                }

                foreach ($collection as $coll) {
                    $this->initializeEntity($coll, $level + 1);
                    $value->add($coll);
                }

                if (count($collection) === $this->collectionSize) {
                    $value->add(sprintf('collection trimmed to %s items', $this->collectionSize));
                }
            }
        }
    }

    private function setExtraLazy($collection): void
    {
        $reflection = new \ReflectionClass($collection);
        $property = $reflection->getProperty('association');
        $property->setAccessible(true);

        $association = $property->getValue($collection);
        $association['fetch'] = 4; // FETCH_EXTRA_LAZY;

        $property->setValue($collection, $association);
    }

    private function getAllProperties($object): array
    {
        $properties = [];
        $reflectionClass = new \ReflectionClass($object);
        do {
            $currentProperties = $reflectionClass->getProperties();
            foreach ($currentProperties as $property) {
                try {
                    $property->setAccessible(true);
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
