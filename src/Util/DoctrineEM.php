<?php

namespace TareqAS\Psym\Util;

use Doctrine\ORM\EntityManagerInterface;

class DoctrineEM
{
    private static $associations = [
        '1' => 'ONE_TO_ONE',
        '2' => 'MANY_TO_ONE',
        '4' => 'ONE_TO_MANY',
        '8' => 'MANY_TO_MANY',
    ];

    public static function findEntityFullName($entityOrTable): string
    {
        $entityOrTable = array_reverse(explode('\\', $entityOrTable))[0];

        foreach (self::getEntitiesTables() as $entityName => $tableName) {
            $entityClassName = array_reverse(explode('\\', $entityName))[0];
            if (
                strtolower($tableName) === strtolower($entityOrTable) ||
                strtolower($entityClassName) === strtolower($entityOrTable)
            ) {
                return $entityName;
            }
        }

        return '';
    }

    public static function getEntitiesTables(): array
    {
        $entitiesTables = [];

        foreach (self::getAllMetadata() as $classMetadata) {
            $entitiesTables[$classMetadata->name] = $classMetadata->table['name'];
        }

        return $entitiesTables;
    }

    public static function getEntities(): array
    {
        return array_keys(self::getEntitiesTables());
    }

    public static function getTables(): array
    {
        return array_values(self::getEntitiesTables());
    }

    public static function getPropertiesColumns($entityName): array
    {
        global $em;
        $properties = [];
        $entityName = self::findEntityFullName($entityName);
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

    public static function getProperties($entityName): array
    {
        return array_keys(self::getPropertiesColumns($entityName));
    }

    public static function getColumns($entityName): array
    {
        return array_values(self::getPropertiesColumns($entityName));
    }

    public static function getEntitiesMappings(): array
    {
        $entities = [];

        foreach (self::getAllMetadata() as $classMetadata) {
            $className = $classMetadata->getName();
            $info = [
                'table' => $classMetadata->getTableName(),
                'properties' => [],
            ];

            foreach ($classMetadata->fieldMappings as $property => $mapping) {
                $type = $mapping['type'];
                $type = $mapping['nullable'] ?? false ? '?'.$type : $type;
                $type = $mapping['unique'] ?? false ? $type.'*' : $type;
                $type = $mapping['id'] ?? false ? $type.' [id]' : $type;
                $type = $mapping['length'] ?? false ? $type." ({$mapping['length']})" : $type;

                $info['properties'][$property] = [
                    'column' => $mapping['columnName'],
                    'type' => $type,
                    'targetEntity' => '',
                ];
            }

            foreach ($classMetadata->associationMappings as $property => $mapping) {
                $columnNames = isset($mapping['joinColumns']) ? array_map(function ($map) {
                    return $map['name'];
                }, $mapping['joinColumns']) : [];
                $mappedBy = isset($mapping['mappedBy']) ? "::\${$mapping['mappedBy']}" : '';
                $type = (self::$associations[$mapping['type']] ?? '')." ({$mapping['targetEntity']}$mappedBy)";

                $info['properties'][$property] = [
                    'column' => join(', ', $columnNames),
                    'type' => $type,
                    'targetEntity' => $mapping['targetEntity'],
                ];
            }

            $properties = [];
            foreach (self::getAllProperties($className) as $name => $value) {
                if (isset($info['properties'][$name])) {
                    $propInfo = $info['properties'][$name];
                    $propInfo['default'] = $value;
                    $properties[$name] = $propInfo;
                }
            }
            $info['properties'] = $properties;

            $entities[$className] = $info;
        }

        return $entities;
    }

    private static function getAllMetadata(): array
    {
        /* @var EntityManagerInterface $em */
        global $em;

        if (!$em) {
            return [];
        }

        return $em->getMetadataFactory()->getAllMetadata();
    }

    private static function getAllProperties($class): array
    {
        $properties = [];
        $reflection = new \ReflectionClass($class);
        do {
            $currentProperties = $reflection->getProperties();
            $defaultProperties = $reflection->getDefaultProperties();

            foreach ($currentProperties as $property) {
                $propertyName = $property->getName();
                $value = null; // null represents lack of value or default value
                if (array_key_exists($propertyName, $defaultProperties)) {
                    $value = Helper::stringifyDefaultValue($defaultProperties[$propertyName]);
                }
                $properties[$propertyName] = $value;
            }
            $reflection = $reflection->getParentClass();
        } while ($reflection);

        return $properties;
    }
}
