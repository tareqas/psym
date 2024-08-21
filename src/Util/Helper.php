<?php

namespace TareqAS\Psym\Util;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class Helper
{
    private static $htmlConfig = ['level', 'nestedLevel', 'size', 'collectionSize', 'maxString'];

    public static function html(...$vars)
    {
        if (!$vars) {
            return;
        }

        $config = [];
        if (count($vars) >= 2) {
            $lastVar = $vars[count($vars) - 1];
            if (is_array($lastVar) && array_intersect(array_keys($lastVar), self::$htmlConfig)) {
                $config = array_pop($vars);
            }
        }

        $start = memory_get_usage();

        DoctrineProxy::init(
            $vars,
            $config['nestedLevel'] ?? $config['level'] ?? -1,
            $config['collectionSize'] ?? $config['size'] ?? 1
        );

        $cloner = new VarCloner();
        $cloner->setMaxString($config['maxString'] ?? -1);
        $cloner->setMaxItems(-1);
        $dumper = new HtmlDumper();

        mkdir($filePath = sys_get_temp_dir().'/psym/dump', 0755, true);
        $filePath = $filePath.'/'.time().'.html';

        $output = fopen($filePath, 'w+');
        $dumper->dump($cloner->cloneVar($vars), $output, [
            'maxStringLength' => $config['maxString'] ?? -1,
            'maxDepth' => -1,
        ]);
        fclose($output);

        $end = memory_get_usage();
        $memoryUsed = $end - $start;

        echo "\n  Memory used: ".number_format($memoryUsed / (1024 * 1024), 2)." MB\n";
        echo "\n  \e]8;;file://$filePath\e\\\033[32m CLICK TO VIEW\033[0m\e]8;;\e\\\n\n";
    }

    public static function partialSearch(array $subjects, string $searchTerm): array
    {
        if (in_array($searchTerm, $subjects)) {
            return [];
        }

        $result = array_filter($subjects, function ($subject) use ($searchTerm) {
            return false !== stripos($subject, $searchTerm) && strlen($subject) !== strlen($searchTerm);
        });

        return array_values($result);
    }

    public static function stringifyDefaultValue($defaultValue): string
    {
        if (is_array($defaultValue)) {
            if (array_keys($defaultValue) !== range(0, count($defaultValue) - 1)) {
                $default = '';
                foreach ($defaultValue as $key => $value) {
                    $default .= "'$key' => '$value', ";
                }
                $default = rtrim($default, ', ');
            } else {
                $default = implode(', ', $defaultValue);
            }
            $default = "[$default]";
        } elseif (is_null($defaultValue)) {
            $default = 'null';
        } elseif (is_bool($defaultValue)) {
            $default = ($defaultValue ? 'true' : 'false');
        } elseif (is_string($defaultValue)) {
            $default = '"'.$defaultValue.'"';
        } else {
            $default = var_export($defaultValue, true);
        }

        return $default;
    }
}
