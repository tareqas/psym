<?php

$defaultFile = __DIR__.'/../composer.json';
$sfFile = __DIR__.'/../composer-sf.json';
$sf7File = __DIR__.'/../composer-sf7.json';

$composer = json_decode(file_get_contents($defaultFile), true);
$replace = [
    'symfony/console' => '*',
    'symfony/var-dumper' => '*',
];

$sfJson = [
    'autoload' => $composer['autoload'],
    'require' => $composer['require'],
    'replace' => $replace,
    'config' => [
        'platform' => ['php' => '7.2.99'],
        'vendor-dir' => 'vendor-sf',
    ],
];

$sf7Json = [
    'autoload' => $composer['autoload'],
    'require' => array_replace($composer['require'], ['psy/psysh' => 'dev-main']),
    'replace' => $replace,
    'config' => [
        'platform' => ['php' => '8.2.99'],
        'vendor-dir' => 'vendor-sf7',
    ],
    'repositories' => [
        [
            'type' => 'vcs',
            'url' => 'https://github.com/tareqas/psysh',
        ],
    ],
];

file_put_contents($sfFile, json_encode($sfJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "composer-sf.json has been created successfully.\n";
file_put_contents($sf7File, json_encode($sf7Json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "composer-sf7.json has been created successfully.\n";
