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

    public $type;
    private $rawSql;
    private $tables = [];

    public function __construct(string $rawSql, string $type)
    {
        if (!in_array($type, $supportedTypes = ['sql', 'dql'])) {
            throw new \Exception(sprintf('Unsupported type: "%s", supported types: %s', $type, join(', ', $supportedTypes)));
        }

        $this->type = $type;
        $this->rawSql = $rawSql;
    }

    public function getTables(): array
    {
        if ($this->tables) {
            return $this->tables;
        }

        $sql = $this->sanitizeSql($this->rawSql);
        $pattern = '/\b(?:FROM|JOIN|UPDATE) ([\w\[\]\\\."\'`]+)(?: AS)?( [\w\[\]\\\."\'`]+)?/i';

        preg_match_all($pattern, trim($sql), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $table = $this->trimQuotes($match[1]);
            $alias = $this->trimQuotes($match[2] ?? '');

            if (false !== stripos($match[0], 'JOIN') && false !== stripos($table, '.')) {
                if ('dql' !== $this->type) {
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
            } elseif ('dql' === $this->type && !$table = DoctrineEM::findEntityFullName($table)) {
                continue;
            }

            $this->tables[] = [
                'table' => $table,
                'alias' => $alias,
            ];
        }

        return $this->tables;
    }

    public function askingFor(array $info): array
    {
        $substring = substr($info['line_buffer'], 0, $info['point']);
        $substring = preg_replace('/^(sql|dql)\(/i', '', $substring);
        $substring = trim($this->removeExtraSpaces($substring), '"\'`');
        $extracted = ['type' => '', 'name' => '', 'alias' => ''];

        if (preg_match('/(\w+)\.(\w+)?$/i', $substring, $match)) {
            $extracted['type'] = 'COLUMN';
            $extracted['name'] = $this->trimQuotes($match[2] ?? '');
            $extracted['alias'] = $this->trimQuotes($match[1] ?? '');
        } elseif (preg_match('/\b(?:FROM|JOIN|UPDATE) ([\w\[\]\\\."\'`]+)?$/i', $substring, $match)) {
            $extracted['type'] = 'TABLE';
            $extracted['name'] = $this->trimQuotes($match[1] ?? '');
            $extracted['alias'] = $this->trimQuotes($match[2] ?? '');
        } elseif (preg_match('/(?:\b(\w+)| )$/i', $substring, $match)) {
            $extracted['type'] = 'KEYWORDS';
            $extracted['name'] = $this->trimQuotes($match[1] ?? '');
        } elseif ('' === $substring) {
            $extracted['type'] = 'KEYWORDS_STARTING';
        }

        return $extracted;
    }

    public function findByTableOrAlias(string $tableOrAlias): array
    {
        if (!$tableOrAlias) {
            return [];
        }

        $result = array_filter($this->getTables(), function ($tab) use ($tableOrAlias) {
            return $tab['table'] === $tableOrAlias || $tab['alias'] === $tableOrAlias;
        });

        return reset($result) ?: [];
    }

    private function sanitizeSql(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', strtolower($sql));

        return $this->trimQuotes($this->removeExtraSpaces($sql));
    }

    private function removeExtraSpaces(string $text): string
    {
        return preg_replace('/\s+/', ' ', $text);
    }

    private function trimQuotes(string $text): string
    {
        return trim($text, " \t\n\r\0\x0B'\"`[]");
    }
}
