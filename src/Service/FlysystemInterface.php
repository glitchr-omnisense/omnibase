<?php

namespace Base\Service;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;

/**
 *
 */
interface FlysystemInterface
{
    public static function getProjectDir();

    public static function getPublicDir();

    public function hasStorage(string $storageName): bool;

    public function getStorageNames(bool $public = true): array;

    public function getDefaultStorage(): FilesystemOperator;

    public function setDefaultStorage(FilesystemOperator|string $operator);

    public function getPublic(mixed $path, FilesystemOperator|string|null $operator = null);

    public function getPublicRoot(FilesystemOperator|string|null $operator = null): ?string;

    public function getOperator(FilesystemOperator|string|null $operator = null): FilesystemOperator;

    public function getAdapter(FilesystemOperator|string|null $operator = null): FilesystemAdapter;

    public function prefixPath(string $path, FilesystemOperator|string|null $operator = null);

    public function stripPrefix(string $path, FilesystemOperator|string|null $operator = null);

    public function read(string $path, FilesystemOperator|string|null $operator = null): ?string;

    public function write(string $path, string $contents, FilesystemOperator|string|null $operator = null, array $config = []): bool;

    public function delete(string $path, FilesystemOperator|string|null $operator = null): bool;

    public function fileExists(string $path, FilesystemOperator|string|null $operator = null): bool;

    public function mkdir(string $path, FilesystemOperator|string|null $operator = null, array $config = []): bool;

    public function mimeType(string $path, FilesystemOperator|string|null $operator = null): ?string;

    public function get(mixed $path, FilesystemOperator|string|null $operator = null);
}
