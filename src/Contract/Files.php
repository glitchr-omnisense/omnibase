<?php

namespace Base\Contract;

/**
 * The stored files as an entity sees them: where a file is served from and
 * where PHP can read it, by its path in a storage. Nothing of how it is
 * stored (Flysystem, a disk, a bucket): that stays with the storage.
 *
 * The Fondation's own contract (plan "omnibase agnostique", C4): what
 * Base\Database\Attribute\Uploader and the entities' file getters need of the
 * storages, without the Flysystem service. A storage is named as in
 * omnibase's configuration ("local.cache", "local.public", "s3.private"...),
 * null for the default one. Under Symfony, Base\Bridge\Symfony\FlysystemFiles:
 * the answers of Base\Service\Flysystem (and of MediaService's image route for
 * a remote storage, as the uploader resolves them).
 */
interface Files
{
    /**
     * What the uploader hands an entity for a stored file: its path under the
     * public directory, which the web server serves (a local storage linked
     * there: /srv/app/public/uploads/...), or for a remote storage the address
     * of the image route; null when it has neither (a missing file, a private
     * storage).
     */
    public function publicPath(string $path, ?string $storage = null): ?string;

    /**
     * The web address of a stored file: its public path less the public
     * directory (/uploads/...), or the image route's; null as publicPath().
     */
    public function publicUrl(string $path, ?string $storage = null): ?string;

    /**
     * The file's path on this machine (a local storage, its root prefixed),
     * null where there is no local file behind it (a remote storage).
     */
    public function localPath(string $path, ?string $storage = null): ?string;

    /** Whether the storage is remote: no file on this machine behind its paths. */
    public function isRemote(?string $storage = null): bool;
}
