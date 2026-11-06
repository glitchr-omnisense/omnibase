<?php

namespace Base\Console\Task;

use Base\Services;
use Base\Service\Model\ClassFinder;

/**
 * Where the console tasks look for classes (plan "omnibase agnostic", step F5e, slice 3): the core's src/ folder
 * (it was BaseBundle::getBundleDir().'/src'), and the classes of a folder in the order the filesystem lists them.
 */
final class Folders
{
    /** The core's src/ folder. */
    public static function core(): string
    {
        return \dirname((string) (new \ReflectionClass(Services::class))->getFileName());
    }

    /**
     * The classes of a folder, as AbstractBaseBundle::getAllClasses() answered them: by their full names, or relative
     * to a root namespace ($prefix: "App", "Base") - a class of another namespace by its short name.
     *
     * @return list<string>
     */
    public static function classes(string $folder, string $prefix = ''): array
    {
        $classes = ClassFinder::listed($folder);
        if ('' === $prefix) {
            return $classes;
        }

        return array_values(array_unique(array_map(static fn (string $class): string => str_starts_with($class, $prefix.'\\') ? substr($class, \strlen($prefix) + 1) : substr((string) strrchr('\\'.$class, '\\'), 1), $classes)));
    }
}
