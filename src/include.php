<?php

/**
 * Include this file to any PHP script in a Symfony project to get helpful services
 * like $kernel, $container, $doctrine, and so on.
 */
require_once getcwd().'/vendor/autoload.php';
require_once __DIR__.'/../vendor/autoload.php';

return (new \TareqAS\Psym\SFLoader(getcwd()))->getUsefulServices();
