<?php

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use TareqAS\Psym\Util\DoctrineEM;
use TareqAS\Psym\Util\Helper;

if (!function_exists('html')) {
    /**
     * Dump variables in an HTML file. If variables are doctrine entity objects, it will initialize all of them.
     *
     * @param mixed ...$vars Variables to be dumped. The last argument can optionally be an array of configuration options.
     *                       Example: ['level' or 'nestedLevel' => -1, 'size' or 'collectionSize' => 1, 'maxString' => -1]
     *                       Where:
     *                          - `nestedLevel` or `level` - how deep it should go to instantiate doctrine proxy object
     *                          - `collectionSize` or `size` - cut the Doctrine association collection to this specific size
     *                          - `maxString` - cut the overlong string to this specific size
     * @return void
     */
    function html(...$vars): void
    {
        Helper::html(...$vars);
    }
}

if (!function_exists('table')) {
    /**
     * Retrieve a repository for a given entity.
     *
     * @param string      $table the entity class name or table name
     * @param string|null $alias Optional. QueryBuilder alias. If provided, returns a QueryBuilder.
     *
     * @return EntityRepository|QueryBuilder returns EntityRepository if no alias is provided,
     *                                       otherwise returns QueryBuilder
     */
    function table(string $table, ?string $alias = null)
    {
        global $doctrine;

        $table = DoctrineEM::findEntityFullName($table) ?: $table;
        $repo = $doctrine->getRepository($table);

        return $alias ? $repo->createQueryBuilder($alias) : $repo;
    }
}

if (!function_exists('sql')) {
    /**
     * Execute a raw SQL query and get the result as an associative array.
     *
     * @param string $sql the raw SQL query to execute
     *
     * @return array the result set as an associative array
     */
    function sql(string $sql): array
    {
        global $em;

        $connection = $em->getConnection();
        $stmt = $connection->prepare(trim($sql));
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }
}
