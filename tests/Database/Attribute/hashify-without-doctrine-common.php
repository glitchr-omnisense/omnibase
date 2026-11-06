<?php

/*
 * #[Hashify]'s password hasher where doctrine/common is not installed (F6 slice 2's defect: the core 5.x named its
 * ClassUtils without requiring it): run by docker/bin/bare --strict on the core installed alone for tests/BareTest.php.
 * The entity's class is the unit of work's className() where a persistence is installed, else the class itself (a
 * Doctrine proxy's marker taken off).
 */

use Base\Database\Attribute\Hashify;

$hasher = (new Hashify(algorithm: 'plaintext'))->getMessageHasher(new class {
});

return [
    'doctrine/common' => class_exists('Doctrine\\Common\\Util\\ClassUtils'),
    'hasher' => $hasher::class,
    'hashed' => $hasher->hash('secret'),
];
