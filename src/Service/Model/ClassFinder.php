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

    /**
     * The classes of a folder (or of one file) in the order the filesystem lists them, the interfaces and the
     * empty files left out: what AbstractBaseBundle::getAllClasses() answers, in its order (the translation
     * keys' commands print them so: plan "omnibase agnostic", step F5e, slice 3).
     *
     * @return list<string>
     */
    public static function listed(string $path): array
    {
        $classes = [];
        foreach (self::files($path) as $file) {
            if (!str_ends_with($file, 'Interface.php')) {
                $classes[] = self::namespaceOf($file).basename($file, '.php');
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * The namespaces the files of a folder declare, in the order the filesystem lists them (an interface's
     * too): what AbstractBaseBundle::getAllNamespaces() answers.
     *
     * @return list<string>
     */
    public static function namespaces(string $path): array
    {
        return array_values(array_unique(array_map(static fn (string $file): string => rtrim(self::namespaceOf($file), '\\'), self::files($path))));
    }

    /** @return list<string> the non-empty PHP files, depth first as the filesystem lists them, dot files left out (Symfony Finder's default) */
    private static function files(string $path): array
    {
        $path = realpath($path);
        if ($path === false) {
            return [];
        }
        if (is_file($path)) {
            return filesize($path) == 0 ? [] : [$path];
        }

        $directories = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $file): bool => !str_starts_with($file->getFilename(), '.'),
        );

        $files = [];
        foreach (new \RecursiveIteratorIterator($directories) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && $file->getSize() > 0) {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }

    private static function namespaceOf(string $file): string
    {
        foreach (file($file) as $line) {
            if (str_starts_with($line, 'namespace ')) {
                return preg_match('/^namespace (\\\\?)(.*);$/', rtrim($line, "\r\n"), $match) && $match[2] !== '' ? $match[2]."\\" : '';
            }
        }

        return '';
    }
}
