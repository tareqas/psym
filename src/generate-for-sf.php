<?php

$composerFile = __DIR__.'/../composer.json';
$sfFile = __DIR__.'/../composer-sf.json';

$composer = json_decode(file_get_contents($composerFile), true);

$fallbackJson = [
    'autoload' => $composer['autoload'],
    'require' => $composer['require'],
    'replace' => [
        'symfony/console' => '*',
        'symfony/var-dumper' => '*',
    ],
    'config' => [
        'vendor-dir' => 'vendor-sf',
    ],
];

file_put_contents($sfFile, json_encode($fallbackJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "composer-sf.json has been created successfully.\n";
