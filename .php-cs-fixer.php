<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['bin', 'src'])
;

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@Symfony' => true,
])
    ->setFinder($finder)
;
