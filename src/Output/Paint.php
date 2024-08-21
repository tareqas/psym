<?php

namespace TareqAS\Psym\Output;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Paint
{
    private static $formatter;

    public static function getFormatter(): OutputFormatter
    {
        if (self::$formatter) {
            return self::$formatter;
        }

        return self::$formatter = new OutputFormatter(true, [
            'b' => new OutputFormatterStyle('green', null, ['bold']),
            'i' => new OutputFormatterStyle('green', null, ['underscore']),
            'em' => new OutputFormatterStyle('green', null, ['bold']),
            'pre' => new OutputFormatterStyle('green', null, ['bold']),
            'code' => new OutputFormatterStyle('green', null, ['bold']),
            'doc' => new OutputFormatterStyle('green'),
            'doc-tag' => new OutputFormatterStyle('magenta'),
            'keyword' => new OutputFormatterStyle('cyan'),
            'name' => new OutputFormatterStyle('red'),
            'search' => new OutputFormatterStyle('red'),
        ]);
    }

    public static function docAndSignature(string $doc, string $signature): array
    {
        $formatter = self::getFormatter();
        $doc = self::sanitizePhpStormDoc($doc);
        $doc = $formatter->format("<doc>$doc</doc>");
        $doc = html_entity_decode($doc);
        $signature = $formatter->format($signature);

        return ["$doc\n$signature\n", ''];
    }

    public static function message(string $message): array
    {
        $message = self::getFormatter()->format($message);

        return ["\n$message\n", ''];
    }

    private static function sanitizePhpStormDoc(string $doc): string
    {
        $doc = preg_replace('#<br>|<p>|</p>#i', "\n * ", $doc);

        $doc = preg_replace('#<ul>|</ul>#i', "\n * ", $doc);
        $doc = preg_replace('#<li>#i', '— ', $doc);
        $doc = preg_replace('#</li>#i', '', $doc);

        $doc = preg_replace('#(<tr[^<]*>\s+\*\s+<td>)|(</td>\s+\*\s+<tr>)#i', '', $doc);
        $doc = preg_replace('#</td>\s+\*\s+<td>#i', ' — ', $doc);

        $doc = preg_replace('#(?<!array|array |non-empty-array|non-empty-array |list|list |non-empty-list|non-empty-list |iterable|iterable |Collection|Collection )<(?!/?(b|i|em)\b)[^>]+>#i', '', $doc);

        $doc = preg_replace('#```(.+?)```#s', '<code>$1</code>', $doc);
        $doc = preg_replace('#(@\w+)#', '<doc-tag>$1</doc-tag>', $doc);
        $doc = preg_replace('#(\$\w+)#', '<name>$1</name>', $doc);

        $doc = preg_replace('#(\n (\* +)(?!\S+)){2,}#', "\n * ", $doc);

        return trim($doc);
    }
}
