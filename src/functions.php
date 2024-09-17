<?php

use Doctrine\DBAL\FetchMode;
use Doctrine\ORM\AbstractQuery;
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
     *                       - `nestedLevel` or `level` - how deep it should go to instantiate doctrine proxy object
     *                       - `collectionSize` or `size` - cut the Doctrine association collection to this specific size
     *                       - `maxString` - cut the overlong string to this specific size
     *
     * @return void
     */
    function html(...$vars)
    {
        Helper::html(...$vars);
    }
}

if (!function_exists('table')) {
    /**
     * Retrieve a repository or query builder for a given entity.
     *
     * @param string      $table the entity class name or table name
     * @param string|null $alias Optional. QueryBuilder alias. If provided, returns a QueryBuilder.
     *
     * @return EntityRepository|QueryBuilder|void returns EntityRepository if no alias is provided,
     *                                            otherwise returns QueryBuilder
     */
    function table(string $table, ?string $alias = null)
    {
        global $doctrine;

        if (!$doctrine) {
            dump('** No Doctrine found! **');

            return;
        }

        $table = DoctrineEM::findEntityFullName($table) ?: $table;
        $repo = $doctrine->getRepository($table);

        return $alias ? $repo->createQueryBuilder($alias) : $repo;
    }
}

if (!function_exists('sql')) {
    /**
     * Executes a raw SQL query and returns the result.
     *
     * @param string $sql    the SQL query string
     * @param array  $params an associative array of query parameters
     *
     * @return array[]|void
     */
    function sql(string $sql, array $params = [])
    {
        global $em;

        if (!$em) {
            dump('** No Doctrine found! **');

            return;
        }

        try {
            $connection = $em->getConnection();
            $stmt = $connection->prepare(trim($sql));

            if (method_exists($stmt, 'executeQuery')) {
                $result = $stmt->executeQuery($params);
                $result = $result->fetchAllAssociative();
            } else {
                $stmt->execute($params);
                $result = $stmt->fetchAll(FetchMode::ASSOCIATIVE);
            }

            return $result;
        } catch (\Doctrine\DBAL\Driver\Exception|\Doctrine\DBAL\Exception $e) {
            dump($e->getMessage());
        }
    }
}

if (!function_exists('dql')) {
    /**
     * Executes a DQL (Doctrine Query Language) query and returns the result.
     *
     * @param string $dql    the DQL query string
     * @param array  $params an associative array of query parameters
     *
     * @return array[]|void
     */
    function dql(string $dql, array $params = [])
    {
        global $em;

        if (!$em) {
            dump('** No Doctrine found! **');

            return;
        }

        $query = $em->createQuery(trim($dql));

        if ($params) {
            $query->setParameters($params);
        }

        return $query->getResult(AbstractQuery::HYDRATE_ARRAY);
    }
}
