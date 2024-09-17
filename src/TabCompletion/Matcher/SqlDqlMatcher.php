<?php

namespace TareqAS\Psym\TabCompletion\Matcher;

use Psy\TabCompletion\Matcher\AbstractMatcher;
use TareqAS\Psym\Parser\SqlParser;
use TareqAS\Psym\TabCompletion\NonCombinableMatcher;
use TareqAS\Psym\Util\DoctrineEM;
use TareqAS\Psym\Util\Helper;

class SqlDqlMatcher extends AbstractMatcher implements NonCombinableMatcher
{
    public function hasMatched(array $tokens): bool
    {
        array_shift($tokens);
        $functionName = array_shift($tokens) ?? [null, null];
        $openBracket = array_shift($tokens) ?? null;
        $nextToken = array_shift($tokens) ?? null;

        if (!in_array($functionName[1], ['sql', 'dql']) || '(' !== $openBracket || ')' === $nextToken) {
            return false;
        }

        if (!function_exists($functionName[1])) {
            return false;
        }

        return true;
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        $functionName = $tokens[1];
        $rawSql = in_array($tokens[3] ?? '', ['"', '`']) ? $tokens[4][1] ?? '' : $tokens[3][1] ?? '';

        return $this->getSuggestions($functionName[1], $rawSql, $info);
    }

    private function getSuggestions(string $type, string $rawSql, array $info): array
    {
        $suggestions = [];
        $sqlParser = new SqlParser($rawSql, $type);
        $found = $sqlParser->askingFor($info);

        switch ($found['type']) {
            case 'TABLE':
                $tables = 'dql' === $sqlParser->type ? DoctrineEM::getEntities() : DoctrineEM::getTables();
                if ($found['name']) {
                    $suggestions = Helper::partialSearch($tables, $found['name']);
                } else {
                    $suggestions = $tables;
                }
                break;
            case 'COLUMN':
                if (!$table = $sqlParser->findByTableOrAlias($found['alias'])) {
                    $suggestions = SqlParser::$keywords;
                    break;
                }
                $properties = 'dql' === $sqlParser->type ? DoctrineEM::getProperties($table['table']) : DoctrineEM::getColumns($table['table']);
                $properties = $found['name'] ? Helper::partialSearch($properties, $found['name']) : $properties;
                $suggestions = array_map(function ($property) use ($found) {
                    return "{$found['alias']}.$property";
                }, $properties);
                break;
            case 'KEYWORDS':
                if ($found['name']) {
                    $suggestions = Helper::partialSearch(SqlParser::$keywords, $found['name']);
                } else {
                    $suggestions = SqlParser::$keywords;
                }
                break;
            case 'KEYWORDS_STARTING':
                $suggestions = SqlParser::$keywordsStarting;
                break;
        }

        return $suggestions;
    }
}
