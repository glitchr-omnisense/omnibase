<?php

namespace Base\Service\Model;

/**
 * The classes a folder of PHP files declares, read from the files: each
 * file's namespace and its name, the interfaces (*Interface.php) and the
 * empty files left out - what Base\Bundle\AbstractBaseBundle::getAllClasses()
 * answers, without loading the bundle (and with it the framework) to ask.
 */
final class ClassFinder
{
    /** @var array<string, list<string>> */
    private static array $classes = [];

    /** @return list<string> */
    public static function classes(string $path): array
    {
        $path = realpath($path);
        if ($path === false) {
            return [];
        }

        if (array_key_exists($path, self::$classes)) {
            return self::$classes[$path];
        }

        $files = [];
        if (is_file($path)) {
            $files[] = $path;
        } else {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getRealPath();
                }
            }
            sort($files);
        }

        $classes = [];
        foreach ($files as $file) {
            if (filesize($file) == 0 || str_ends_with($file, "Interface.php")) {
                continue;
            }

            $namespace = '';
            foreach (file($file) as $line) {
                if (preg_match('/^namespace (\\\\?)(.*);$/', rtrim($line, "\r\n"), $match)) {
                    $namespace = $match[2] !== '' ? $match[2]."\\" : '';
                    break;
                }
            }

            $classes[] = $namespace.basename($file, '.php');
        }

        return self::$classes[$path] = array_values(array_unique($classes));
    }
}
