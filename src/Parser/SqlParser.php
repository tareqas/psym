<?php

namespace TareqAS\Psym\Parser;

use TareqAS\Psym\Util\DoctrineEM;

class SqlParser
{
    public static $keywordsStarting = [
        'select', 'insert', 'update', 'delete', 'create', 'drop', 'alter', 'truncate', 'merge', 'grant',
        'revoke', 'with',
    ];

    public static $keywords = [
        'add', 'all', 'alter', 'and', 'any', 'as', 'asc', 'backup', 'between', 'case', 'check', 'column',
        'constraint', 'create', 'database', 'default', 'delete', 'desc', 'distinct', 'drop', 'exec', 'exists',
        'foreign', 'from', 'full', 'group', 'having', 'in', 'index', 'inner', 'insert', 'is', 'join', 'left',
        'like', 'limit', 'not', 'null', 'on', 'or', 'order', 'outer', 'primary', 'procedure', 'right', 'rownum',
        'select', 'set', 'table', 'top', 'truncate', 'union', 'unique', 'update', 'values', 'view', 'where',
    ];

    private $type;
    private $rawSql;
    private $tables = [];

    public function __construct(string $rawSql, string $type = 'SQL')
    {
        if (!in_array($type, $supportedTypes = ['SQL', 'DQL'])) {
            throw new \Exception(sprintf('Unsupported type: "%s", supported types: %s', $type, join(', ', $supportedTypes)));
        }

        $this->type = strtoupper($type);
        $this->rawSql = $rawSql;
    }

    public function getTables(): array
    {
        if ($this->tables) {
            return $this->tables;
        }

        $sql = $this->sanitizeSql($this->rawSql);
        $pattern = '/\b(?:FROM|JOIN|UPDATE) ([\w\[\]\\\."`]+)(?: AS)?( [\w\[\]"`]+)?/i';

        preg_match_all($pattern, trim($sql), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $table = $this->trimQuotes($match[1]);
            $alias = $this->trimQuotes($match[2] ?? '');

            if (false !== stripos($match[0], 'JOIN') && false !== stripos($table, '.')) {
                if ('DQL' !== $this->type) {
                    continue;
                }

                [$sourceAlias, $sourceColumn] = explode('.', $table);
                if (!$sourceTable = $this->findByTableOrAlias($sourceAlias)) {
                    continue;
                }

                $sourceTable = DoctrineEM::findEntityFullName($sourceTable['table']);
                if (!$mapping = DoctrineEM::getEntitiesMappings()[$sourceTable]) {
                    continue;
                }

                if (!$targetEntity = $mapping['properties'][$sourceColumn]['targetEntity'] ?? '') {
                    continue;
                }

                $table = $targetEntity;
            } elseif ('DQL' === $this->type && !$table = DoctrineEM::findEntityFullName($table)) {
                continue;
            }

            $this->tables[] = [
                'table' => $table,
                'alias' => $alias,
            ];
        }

        return $this->tables;
    }

    public function findByTableOrAlias(string $tableOrAlias): array
    {
        if (!$tableOrAlias) {
            return [];
        }

        $result = array_filter($this->tables, function ($tab) use ($tableOrAlias) {
            return $tab['table'] === $tableOrAlias || $tab['alias'] === $tableOrAlias;
        });

        return reset($result) ?: [];
    }

    public function sanitizeSql(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', strtolower($sql));

        return $this->trimQuotes($sql);
    }

    public function trimQuotes(string $text): string
    {
        return trim($text, " \t\n\r\0\x0B'\"`[]");
    }
}
