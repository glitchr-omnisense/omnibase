<?php

// The bundle is either checked out standalone (CI: own vendor/) or installed
// inside a host application's vendor/glitchr/base-bundle (dev workflow).
foreach ([
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
] as $autoload) {
    if (file_exists($autoload)) {
        // Plain require, not require_once: if the runner already loaded the
        // autoloader, require_once would return true instead of the loader.
        // Composer's autoload.php is idempotent, so this is safe.
        $loader = require $autoload;

        // A host application's autoloader does not know about this bundle's
        // autoload-dev section, so register the test namespace ourselves.
        if ($loader instanceof \Composer\Autoload\ClassLoader) {
            $loader->addPsr4('Tests\\Base\\', __DIR__);
        }

        return;
    }
}

throw new RuntimeException('No composer autoloader found. Run "composer install" first.');
