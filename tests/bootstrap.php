<?php

// The bundle is either checked out standalone (CI: own vendor/) or installed
// inside a host application's vendor/glitchr/base-bundle (dev workflow).
foreach ([
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
] as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;

        return;
    }
}

throw new RuntimeException('No composer autoloader found. Run "composer install" first.');
