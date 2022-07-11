<?php

namespace Base\Service;

use Base\Exception\NotDeletableException;
use Base\Exception\NotReadableException;
use Base\Exception\NotWritableException;

use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\FlysystemBundle\Lazy\LazyFactory;
use Symfony\Component\Finder\Finder;
use InvalidArgumentException;
use ReflectionException;

interface FlysystemInterface
{
    public static function getProjectDir();
    public static function getPublicDir();

    public function hasStorage(string $storage):bool;
    public function getStorageNames():array;
    public function getDefaultStorage(): FilesystemOperator;
    public function setDefaultStorage(FilesystemOperator|string $operator);

    public function getPublic(mixed $path, FilesystemOperator|string|null $operator = null);
    public function getOperator(FilesystemOperator|string|null $operator = null): FilesystemOperator;
    public function getAdapter(FilesystemOperator|string|null $operator = null): FilesystemAdapter;

    public function prefixPath(string $path, FilesystemOperator|string|null $operator = null);
    public function stripPrefix(string $path, FilesystemOperator|string|null $operator = null);
    public function read(string $path, FilesystemOperator|string|null $operator = null): ?string;
    public function write(string $path, string $contents, FilesystemOperator|string|null $operator = null, array $config = []);
    public function delete(string $path, FilesystemOperator|string|null $operator = null);
    public function fileExists(string $path, FilesystemOperator|string|null $operator = null): bool;
    public function mkdir(string $path, FilesystemOperator|string|null $operator = null, array $config = []);
    public function mimeType(string $path, FilesystemOperator|string|null $operator = null) : ?string;
    public function get(mixed $path, FilesystemOperator|string|null $operator = null);
}