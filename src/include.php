<?php

/**
 * Include this file to any PHP script in a Symfony project to get helpful services
 * like $kernel, $container, $doctrine, and so on.
 */
require_once getcwd().'/vendor/autoload.php';
require __DIR__.'/SFChecker.php';

$sfChecker = new \TareqAS\Psym\SFChecker(getcwd());

if ($sfChecker->isSymfony7()) {
    require __DIR__.'/../vendor-sf7/autoload.php';
} else {
    require __DIR__.'/../vendor-sf/autoload.php';
}

return (new \TareqAS\Psym\SFLoader(getcwd()))->getUsefulServices();
