<?php

use Symfony\Component\VarDumper\VarDumper;
use TareqAS\Psym\Helper;
use TareqAS\Psym\ProxyInitializer;

if (!function_exists('html')) {
    function html(...$vars) {
        $_SERVER['VAR_DUMPER_FORMAT'] = 'html';

        if (!$vars) {
            return;
        }

        $start = memory_get_usage();
        if (is_array($vars[0])) {
            $entities = Helper::getEntities();
            foreach ($vars[0] as $var) {
                if (is_object($var) && in_array(get_class($var), $entities)) {
                    ProxyInitializer::init($var);
                }
            }
        }

        ob_start(null, 1024 * 1024 * 1024);
        VarDumper::dump($vars);
        $bufferSize = ob_get_length();
        $dumpOutput = ob_get_clean();

        $end = memory_get_usage();
        $memoryUsed = $end - $start;
        Helper::cleanEntityManager();

        $uniqueId = uniqid(mt_rand());
        $filePath = sys_get_temp_dir().'/'.$uniqueId.'.html';
        file_put_contents($filePath, $dumpOutput);

        echo "\n  Buffer size: ".number_format($bufferSize / (1024 * 1024), 2)." MB";
        echo "\n  Memory used: ".number_format($memoryUsed / (1024 * 1024), 2)." MB\n";
        echo "\n  \e]8;;file://$filePath\e\\\033[32mCLICK TO VIEW\033[0m\e]8;;\e\\\n\n";
    }
}

if (!function_exists('table')) {
    function table($table) {
        global $doctrine;
        $table = Helper::findEntityName($table) ?: $table;
        return $doctrine->getRepository($table);
    }
}

if (!function_exists('sql')) {
    function sql($sql) {
        global $em;
        $connection = $em->getConnection();
        $stmt = $connection->prepare(trim($sql));
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }
}
