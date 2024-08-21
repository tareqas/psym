<?php

namespace TareqAS\Psym\TabCompletion;

use Psy\TabCompletion\AutoCompleter as BaseAutoCompleter;
use Psy\TabCompletion\Matcher\AbstractMatcher;

class AutoCompleter extends BaseAutoCompleter
{
    public function processCallback(string $input, int $index, array $info = []): array
    {
        $line = $info['line_buffer'];
        if (isset($info['end'])) {
            $line = \substr($line, 0, $info['end']);
        }
        if ('' === $line && '' !== $input) {
            $line = $input;
        }

        $tokens = \token_get_all('<?php '.$line);

        $tokens = \array_filter($tokens, function ($token) {
            return !AbstractMatcher::tokenIs($token, AbstractMatcher::T_WHITESPACE);
        });

        $matches = [];
        foreach ($this->matchers as $matcher) {
            if ($matcher->hasMatched($tokens)) {
                $foundMatches = $matcher->getMatches($tokens, $info);

                if ($foundMatches && $matcher instanceof NonCombinableMatcher) {
                    return $foundMatches;
                }

                $matches = \array_merge($foundMatches, $matches);
            }
        }

        $matches = \array_unique($matches);

        return !empty($matches) ? $matches : [''];
    }
}
