<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['bin', 'src', 'tests'])
    ->notPath('Fixtures')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@Symfony' => true,
])
    ->setFinder($finder)
;
