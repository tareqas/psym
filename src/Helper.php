<?php

namespace TareqAS\Psym;

class Helper
{
    public static function cleanEntityManager() {
        global $em;
        $em->clear();
    }

    public static function getEntities(): array
    {
        global $em;
        $entities = [];
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        foreach ($metadata as $classMetadata) {
            $entities[] = $classMetadata->getName();
        }
        sort($entities);

        return $entities;
    }

    public static function getProperties($entityName): array
    {
        global $em;
        $properties = [];
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        foreach ($metadata as $classMetadata) {
            if ($classMetadata->getName() !== $entityName) {
                continue;
            }
            $properties = $classMetadata->columnNames;
            break;
        }

        return $properties;
    }

    public static function findEntityName($entityName): string
    {
        $entities = self::getEntities();
        foreach ($entities as $entity) {
            $entityClassName = array_reverse(explode('\\', $entity))[0];
            if (strtolower($entityClassName) === strtolower($entityName)) {
                return $entity;
            }
        }
        return '';
    }
}
