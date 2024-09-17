<?php

namespace TareqAS\Psym\TabCompletion\Matcher;

use Psy\TabCompletion\Matcher\AbstractContextAwareMatcher;
use TareqAS\Psym\Output\Paint;
use TareqAS\Psym\Parser\Reflection;
use TareqAS\Psym\TabCompletion\NonCombinableMatcher;
use TareqAS\Psym\Util\Helper;

class MethodChainingMatcher extends AbstractContextAwareMatcher implements NonCombinableMatcher
{
    private $tokens = [];
    private $supportTokens = [];

    public function hasMatched(array $tokens): bool
    {
        array_shift($tokens);
        $this->tokens = $this->preprocessTokens($tokens);
        $this->supportTokens = [T_VARIABLE, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, X_STATIC_METHOD];

        $parentheses = [];
        $nextTokenName = null;
        $prevTokenName = null;

        foreach ($this->tokens as $index => $token) {
            if ($this->ignoreVariableAssignment($index)) {
                continue;
            }

            if ($prevTokenName && $this->isWithinParameterParentheses($token, $parentheses)) {
                if (in_array($prevTokenName, $this->supportTokens) && $this->isLast($index)) {
                    return true;
                }
                continue;
            }

            // [supportTokens] (-> T_STRING (->)?)*
            if (!$nextTokenName && in_array($token[0], $this->supportTokens)) {
                $nextTokenName = T_OBJECT_OPERATOR;
                $prevTokenName = $token[0];
            } elseif ($token[0] === $nextTokenName) {
                $nextTokenName = T_STRING === $nextTokenName ? T_OBJECT_OPERATOR : T_STRING;
                $prevTokenName = $token[0];
            } else {
                return false;
            }

            if ($nextTokenName && $this->isLast($index)) {
                return true;
            }
        }

        return false;
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        array_shift($tokens);
        $this->tokens = $this->preprocessTokens($tokens);

        $input = $info['line_buffer'];
        $hasEndingSpace = ' ' === substr($info['line_buffer'], -1);

        $type = '';
        $identifier = '';
        $stack = [];
        $suggestions = [];
        $parentheses = [];

        foreach ($this->tokens as $index => $token) {
            if ($this->ignoreVariableAssignment($index)) {
                continue;
            }

            if ($this->isWithinParameterParentheses($token, $parentheses)) {
                // asking function or method signature while writing params, foo(....
                if ($stack && $this->isLast($index)) {
                    // Adding ")" to this list disrupts other matchers.
                    if (!in_array(substr($input, -1), [' ', '(', '>'])) {
                        preg_match('/\b(\w+)\b$/', $input, $matches);

                        return [$matches[1] ?? $input];
                    }
                    ['token' => $token, 'type' => $type] = array_pop($stack);
                    $suggestions = $this->getSuggestions($token, $type, true);
                }
                continue;
            }

            if (in_array($token[0], $this->supportTokens)) {
                if ($this->isLast($index)) {
                    $suggestions = $this->getSuggestions($token, $type, $hasEndingSpace);
                    break;
                }

                $next = $this->getNextType($token, $type);
                if (!$next['type'] && $next['unknown']) {
                    $suggestions = $this->getMessageWithInvalidPosition($index, 'unknown');
                    break;
                }

                $stack[] = compact('token', 'type', 'identifier');
                $type = $next['type'];
                $identifier = $next['identifier'];
            } elseif (T_OBJECT_OPERATOR === $token[0]) {
                if (!$this->isMethodClosedProperly($identifier, $index)) {
                    $suggestions = $this->getMessageWithInvalidPosition(max($index - 1, 0), 'Invalid method closing');
                    break;
                }
                $suggestions = $this->getSuggestions($token, $type);
            } else {
                break;
            }
        }

        return $suggestions;
    }

    private function ignoreVariableAssignment($currentIndex): bool
    {
        if (count($this->tokens) >= 2 && $currentIndex < 2 && T_VARIABLE === $this->tokens[0][0] && '=' === $this->tokens[1]) {
            return true;
        }

        return false;
    }

    private function isWithinParameterParentheses($token, &$parentheses): bool
    {
        if ('(' === $token) {
            $parentheses[] = $token;
        } elseif (')' === $token) {
            array_pop($parentheses);
        }

        return !empty($parentheses) || ')' === $token;
    }

    private function isLast($currentIndex): bool
    {
        return !isset($this->tokens[$currentIndex + 1]);
    }

    private function isMethodClosedProperly($identifier, $currentIndex): bool
    {
        if ('method' !== $identifier || !isset($this->tokens[$currentIndex - 1])) {
            return true;
        }

        return ')' === $this->tokens[$currentIndex - 1];
    }

    private function getMessageWithInvalidPosition($position, $message): array
    {
        $input = '';
        foreach ($this->tokens as $index => $token) {
            $token = is_array($token) ? $token[1] : $token;
            if ($index === $position) {
                $input .= "<error>$token</error>";
            } else {
                $input .= $token;
            }
        }

        return Paint::message(" ** $message: $input");
    }

    private function getNextType(array $token, string $type = ''): array
    {
        $info = ['type' => '', 'identifier' => '', 'unknown' => false];
        $name = $token[1];

        if (T_VARIABLE === $token[0]) {
            try {
                $info['identifier'] = 'prop';
                $var = str_replace('$', '', $name);
                $object = $this->getVariable($var);
                if (is_object($object)) {
                    $info['type'] = get_class($object);
                }
            } catch (\Exception $e) {
                $info['unknown'] = true;
            }

            return $info;
        }

        if (!in_array($token[0], $this->supportTokens)) {
            $info['unknown'] = true;

            return $info;
        }

        if (X_STATIC_METHOD === $token[0]) {
            [$type, $name] = explode('::', $token[1]);
        }

        // for function, it cannot have type. Otherwise, foo from A\B::foo() will check global function foo()
        if (!$type && function_exists($token[1])) {
            $function = Reflection::getFunction($token[1]);
            $info['identifier'] = 'method';
            $info['type'] = $function['type'];

            return $info;
        }

        if ($class = Reflection::getClassFromType($type, $name)) {
            $info['identifier'] = $class['identifier'];
            $info['type'] = $class['type'];
        } else {
            $info['unknown'] = true;
        }

        return $info;
    }

    private function getSuggestions(array $token, ?string $type = null, bool $showDetails = false): array
    {
        $name = T_OBJECT_OPERATOR === $token[0] ? null : $token[1];

        if (X_STATIC_METHOD === $token[0]) {
            [$type, $name] = explode('::', $token[1]);
        }

        if ($type && $suggestions = $this->getSuggestionsForClass($type, $name, $showDetails)) {
            return $suggestions;
        }

        // for function, it cannot have type. Otherwise, foo from A\B::foo() will check global function foo()
        if (!$type && $suggestions = $this->getSuggestionsForFunction($name, $showDetails)) {
            return $suggestions;
        }

        return [];
    }

    private function getSuggestionsForFunction($functionName, $showDetails = false): array
    {
        $functions = get_defined_functions();
        $functions = array_merge($functions['internal'], $functions['user']);

        if (!$functions) {
            return [];
        }

        $functions = Helper::partialSearch($functions, $functionName);
        $suggestions = array_map(function ($function) { return "$function()"; }, $functions);

        if ($showDetails && $function = Reflection::getFunction($functionName)) {
            $suggestions = Paint::docAndSignature($function['doc'], $function['signature']);
        }

        return $suggestions;
    }

    private function getSuggestionsForClass($type, $propOrMethodName = null, $showDetails = false): array
    {
        if (!$class = Reflection::getClassFromType($type)) {
            return [];
        }

        $methods = array_map(function ($class) {
            return $class['name'];
        }, array_merge($class['staticMethods'], $class['methods']));

        $properties = array_map(function ($property) {
            return $property['name'];
        }, array_merge($class['staticProperties'], $class['properties']));

        if (!$propOrMethodName) {
            $methods = array_map(function ($method) { return "$method()"; }, $methods);

            return array_merge($methods, $properties);
        }

        $methods = Helper::partialSearch($methods, $propOrMethodName);
        $methods = array_map(function ($method) { return "$method()"; }, $methods);
        $properties = Helper::partialSearch($properties, $propOrMethodName);
        $suggestions = array_merge($methods, $properties);

        if ($showDetails && $item = Reflection::getClassItemFromType($type, $propOrMethodName)) {
            $suggestions = Paint::docAndSignature($item['doc'], $item['signature']);
        }

        return $suggestions;
    }

    private function preprocessTokens(array $tokens): array
    {
        if (!defined('T_NAME_FULLY_QUALIFIED')) {
            define('T_NAME_FULLY_QUALIFIED', 10001);
        }
        if (!defined('T_NAME_QUALIFIED')) {
            define('T_NAME_QUALIFIED', 10002);
        }
        if (!defined('X_STATIC_METHOD')) {
            define('X_STATIC_METHOD', 10003);
        }

        $i = 0;
        $count = count($tokens);
        $newTokens = [];

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_array($token) && T_NS_SEPARATOR === $token[0]) {
                ++$i;
                $name = '';

                while ($i < $count && is_array($tokens[$i]) && T_STRING === $tokens[$i][0]) {
                    $name .= '\\'.$tokens[$i][1];
                    $i += 2;
                }

                $newTokens[] = [T_NAME_FULLY_QUALIFIED, $name, $token[2]];
            } elseif (is_array($token) && T_STRING === $token[0]) {
                ++$i;
                $name = $token[1];
                $qualified = false;

                while ($i < $count && is_array($tokens[$i]) && T_NS_SEPARATOR === $tokens[$i][0]) {
                    $qualified = true;
                    $name .= '\\'.$tokens[$i + 1][1];
                    $i += 2;
                }

                if ($qualified) {
                    $newTokens[] = [T_NAME_FULLY_QUALIFIED, '\\'.$name, $token[2]];
                } else {
                    $newTokens[] = [T_STRING, $name, $token[2]];
                }
            } else {
                ++$i;
                $newTokens[] = $token;
            }
        }

        for ($i = 0; $i < count($newTokens); ++$i) {
            if (!isset($newTokens[$i - 1]) || !isset($newTokens[$i]) || !isset($newTokens[$i + 1])) {
                continue;
            }

            if (!in_array($newTokens[$i - 1][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) ||
                T_DOUBLE_COLON !== $newTokens[$i][0] ||
                T_STRING !== $newTokens[$i + 1][0]
            ) {
                continue;
            }

            $token = $newTokens[$i - 1][1].$newTokens[$i][1].$newTokens[$i + 1][1];
            $newTokens[$i - 1] = [X_STATIC_METHOD, $token, $newTokens[$i + 1][2]];
            unset($newTokens[$i], $newTokens[$i + 1]);
            ++$i;
        }

        return array_values($newTokens);
    }
}
