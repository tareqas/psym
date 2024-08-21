<?php

namespace TareqAS\Psym\TabCompletion\Matcher;

use Psy\TabCompletion\Matcher\AbstractMatcher;
use TareqAS\Psym\Parser\SqlParser;
use TareqAS\Psym\TabCompletion\NonCombinableMatcher;
use TareqAS\Psym\Util\DoctrineEM;
use TareqAS\Psym\Util\Helper;

class SqlFunctionMatcher extends AbstractMatcher implements NonCombinableMatcher
{
    public function hasMatched(array $tokens): bool
    {
        array_shift($tokens);
        $functionName = array_shift($tokens) ?? [null, null];
        $openBracket = array_shift($tokens) ?? null;
        $nextToken = array_shift($tokens) ?? null;

        if ('sql' !== $functionName[1] || '(' !== $openBracket || ')' === $nextToken) {
            return false;
        }

        if (!function_exists($functionName[1])) {
            return false;
        }

        return true;
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        $rawSql = in_array($tokens[3] ?? '', ['"', '`']) ? $tokens[4][1] ?? '' : $tokens[3][1] ?? '';

        return $this->getSuggestions($rawSql);
    }

    private function getSuggestions(string $rawSql): array
    {
        $sqlParser = new SqlParser($rawSql, 'SQL');
        $sql = $sqlParser->trimQuotes($rawSql);

        $tables = DoctrineEM::getTables();
        $foundTables = array_map(function ($table) {
            return $table['table'];
        }, $sqlParser->getTables());

        if (!$sql) {
            return SqlParser::$keywordsStarting;
        }

        if ($match = Helper::partialSearch(SqlParser::$keywordsStarting, $sql)) {
            return $match;
        }

        // select c.id, c.order_id (...)
        if (!$foundTables) {
            return $tables;
        }

        // partial tables
        $matches = array_reduce($foundTables, function ($carry, $foundTable) use ($tables) {
            $result = Helper::partialSearch($tables, $foundTable);

            return array_unique(array_merge($carry, $result));
        }, []);

        if ($matches) {
            return $matches;
        }

        // select c.id, c.(...) from cart c where c.
        if (preg_match_all('/(\w+)\.(\s|\w+)?/', $sql, $matches, PREG_SET_ORDER)) {
            $partialMatches = [];
            $emptyMatches = [];

            foreach ($matches as $match) {
                $alias = $match[1];
                $column = trim($match[2] ?? '');

                if (!$foundTable = $sqlParser->findByTableOrAlias($alias)) {
                    continue;
                }
                $properties = DoctrineEM::getColumns($foundTable['table']);

                if ($column) { // partial properties
                    $properties = Helper::partialSearch($properties, $column);
                    $properties = array_map(function ($property) use ($alias) {
                        return "$alias.$property";
                    }, $properties);
                    $partialMatches = array_unique(array_merge($partialMatches, $properties));
                } else { // no properties
                    $properties = array_map(function ($property) use ($alias) {
                        return "$alias.$property";
                    }, $properties);
                    $emptyMatches = array_unique(array_merge($emptyMatches, $properties));
                }
            }

            if ($partialMatches) {
                return array_unique($partialMatches);
            }

            if ($emptyMatches) {
                return array_unique($emptyMatches);
            }
        }

        if (' ' === substr($rawSql, -1)) {
            return SqlParser::$keywords;
        }

        if ($lastToken = array_reverse(explode(' ', $sql))[0]) {
            return Helper::partialSearch(SqlParser::$keywords, $lastToken);
        }

        return [];
    }
}
