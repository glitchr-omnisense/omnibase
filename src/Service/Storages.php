<?php

namespace Base\Service;

use Omnidrive\Drive;
use Omnidrive\Drives;

/**
 * omnibase's named storages over omniscient/omnidrive (audit "lesringostars
 * face au master plan", step R28): what the core asks of them - a drive by
 * its name (getDrive(), omnidrive's Drive: read, write, list, public address,
 * local path...), and omnibase's rule of the public paths (getPublic(),
 * prefixPath(), stripPrefix(): a stored file's address under public/, by the
 * storage's prefix) with the checks it makes before a read or a write. Nothing
 * of Flysystem in or out: a storage is named by a string.
 *
 * Base\Service\Flysystem implements it, Flysystem behind omnidrive's drives;
 * it replaces the deprecated FlysystemInterface (Flysystem's operators and
 * adapters in its signatures) for every user of the core.
 */
interface Storages
{
    /** @return string */
    public static function getProjectDir();

    /** @return string */
    public static function getPublicDir();

    /** The storages, as omnidrive's drives. */
    public function getDrives(): Drives;

    /** A storage as an omnidrive drive: by its name, the default one when null. */
    public function getDrive(?string $storage = null): Drive;

    public function hasStorage(string $storageName): bool;

    /** @return array<int, string> the storages' names, without the ".public" ones unless $public */
    public function getStorageNames(bool $public = true): array;

    /**
     * The public address of a stored path (the storage's public root for "" or "/"), null when it has none.
     *
     * @return string|null
     */
    public function getPublic(mixed $path, ?string $storage = null);

    public function getPublicRoot(?string $storage = null): ?string;

    /** @return string */
    public function prefixPath(string $path, ?string $storage = null);

    /** @return string */
    public function stripPrefix(string $path, ?string $storage = null);

    /** The contents, null when there is no such file. */
    public function read(string $path, ?string $storage = null): ?string;

    /**
     * Writes a new file: false when one is there already.
     *
     * @param array<string, mixed> $config
     */
    public function write(string $path, string $contents, ?string $storage = null, array $config = []): bool;

    /** Deletes a file: false when there is none. */
    public function delete(string $path, ?string $storage = null): bool;

    public function fileExists(string $path, ?string $storage = null): bool;

    /**
     * Whether no file of this machine is behind the storage's paths (S3, SFTP, FTP...).
     *
     * @return bool
     */
    public function isRemote(?string $storage = null);

    /** @param array<string, mixed> $config */
    public function mkdir(string $path, ?string $storage = null, array $config = []): bool;

    public function mimeType(string $path, ?string $storage = null): ?string;

    /**
     * The stored path, prefixed, when the file exists.
     *
     * @return string|null
     */
    public function get(mixed $path, ?string $storage = null);
}
