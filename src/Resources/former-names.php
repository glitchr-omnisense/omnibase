<?php

/*
 * The former names of the classes that moved from the core to omnibridge/doctrine
 * (plan "omnibase agnostic", step F3): Base\Bridge\Doctrine\* is
 * Omnibridge\Doctrine\Omnibase\*, and every other one keeps its path under
 * Omnibridge\Doctrine\Omnibase\ - Base\Database\Mapping\ClassMetadataManipulator is
 * Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator,
 * Base\Repository\UserRepository is Omnibridge\Doctrine\Omnibase\Repository\UserRepository.
 * The list is former-names.doctrine.php (former => new).
 *
 * The same for the Symfony form layer (step F5b), former-names.symfony.php: Base\Field\Type\*,
 * Base\Form\{Type, Extension, Transformer, Traits}\*, FormFactory, FormProxy, FormProcessor and
 * their interfaces keep their path under Omnibridge\Symfony\Omnibase\, resolved only where
 * omnibridge/symfony is installed.
 *
 * Each former name is the moved class under its former name, a class_alias the
 * core declares - resolved only where omnibridge/doctrine is installed:
 * - when the former name is asked first (an entity's repositoryClass, a site's
 *   repository extending Base\Repository\UserRepository, a doctrine.yaml naming
 *   Base\Database\Mapping\ClassMetadataFactory), the moved class is loaded and
 *   aliased;
 * - when the moved class is loaded first (by its new name, or as the parent of
 *   another), its former name is aliased at once, so that `instanceof` and a type
 *   written with the former name - which PHP checks without autoloading - answer
 *   as they did.
 * The autoloader is prepended, and answers only for the names of the list.
 *
 * Required by Functions.php, which every install of the core autoloads. Kept
 * until 6.0 (after step S, where the sites and bundles name the new classes);
 * no deprecation is raised meanwhile, as the core's own entities still name the
 * repositories by their former names (Doctrine's metadata, MetadataSnapshot).
 */

namespace Base\Resources;

use Composer\Autoload\ClassLoader;

(static function (): void {
    /** @var array<class-string, class-string> $moved */
    $moved = (require __DIR__.'/former-names.doctrine.php') + (require __DIR__.'/former-names.symfony.php');
    $former = array_flip($moved);

    $exists = static fn (string $class, bool $autoload): bool => class_exists($class, $autoload) || interface_exists($class, $autoload) || trait_exists($class, $autoload);

    spl_autoload_register(static function (string $class) use ($moved, $former, $exists): void {
        if (isset($moved[$class])) {
            if ($exists($moved[$class], true) && !$exists($class, false)) {
                class_alias($moved[$class], $class);
            }

            return;
        }

        // The moved class asked by its new name: PHP does not autoload a class again while it is being
        // autoloaded, so its file is found by Composer's loaders and included here, then its former name aliased.
        if (isset($former[$class])) {
            foreach (ClassLoader::getRegisteredLoaders() as $loader) {
                if (false !== $file = $loader->findFile($class)) {
                    (static function (string $file): void { include $file; })($file);
                    break;
                }
            }
            if ($exists($class, false) && !$exists($former[$class], false)) {
                class_alias($class, $former[$class]);
            }
        }
    }, true, true);
})();
