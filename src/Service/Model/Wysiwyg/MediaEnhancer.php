<?php

namespace Base\Service\Model\Wysiwyg;

use Base\Imagine\FilterInterface;
use Base\Service\FlysystemInterface;
use Base\Service\MediaServiceInterface;
use Base\Traits\BaseTrait;
use Psr\Log\LoggerInterface;

class MediaEnhancer implements MediaEnhancerInterface
{
    use BaseTrait;

    /**
     * @var MediaServiceInterface
     */
    protected MediaServiceInterface $mediaService;

    /**
     * @var FlysystemInterface
     */
    protected FlysystemInterface $flysystem;

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger;

    public function __construct(MediaServiceInterface $mediaService, FlysystemInterface $flysystem, ?LoggerInterface $logger = null)
    {
        $this->mediaService = $mediaService;
        $this->flysystem = $flysystem;
        $this->logger = $logger;
    }

    public function enhance(string|array|null $strOrArray, array $config = [], FilterInterface|array $filters = [], array $attributes = []): string|array|null
    {
        if (!$strOrArray) {
            return $strOrArray;
        }

        $array = is_array($strOrArray) ? $strOrArray : [$strOrArray];

        foreach($array as &$entry) {
            if(!$entry) continue;

            // DECODE FIRST: If path is already an obfuscated/routed URL, decode to get original path
            // This prevents nested encoding
            if (str_starts_with($entry, '/images/')) {
                $resolved = $this->mediaService->resolve($entry);
                if ($resolved && isset($resolved["path"])) {
                    // Extract the original path from the encoded URL
                    $entry = $resolved["path"];
                }
            }

            // Verify the source exists before processing
            $sourcePath = $this->resolveSourcePath($entry, $config);

            if (!$sourcePath) {
                // Path not found - keep original path unchanged.
                // An external URL ("https://...") is legitimately none of our
                // business, but a site-relative path we failed to resolve is a
                // guaranteed 404 for the visitor: it is emitted verbatim into the
                // page and served by the webserver, never by MediaController, so
                // it bypasses the no-image fallback entirely. Make it visible.
                if (str_starts_with($entry, "/") && !str_starts_with($entry, "//")) {
                    $this->logger?->warning("MediaEnhancer: unresolved media path \"{path}\", emitting it unchanged (expect a 404).", [
                        "path" => $entry,
                        "storage" => $config["storage"] ?? null,
                    ]);
                }

                continue;
            }

            // ENCODE ONCE: Generate obfuscated URL through media service.
            // Local storage: the ABSOLUTE filesystem path (not web-relative) so it
            // can be properly cached. Remote storage: the storage-relative path,
            // resolved against $config["storage"] — MediaService streams the source
            // from the remote and caches the derivative locally. Same contract as
            // EditorController's upload endpoints.
            $obfuscatedUrl = $this->mediaService->image($sourcePath, $config, $filters);

            // If encoding succeeded, use the obfuscated URL
            // This ensures ALL paths end up as /images/XX/XX/.../image.webp
            if ($obfuscatedUrl && is_string($obfuscatedUrl)) {
                $entry = $obfuscatedUrl;
            }
        }

        return is_array($strOrArray) ? $array : first($array);
    }

    /**
     * Path to hand to MediaService for this web path, or null if nothing backs it.
     *
     * Returns an absolute filesystem path for a local storage, and the
     * storage-relative path for a remote one (S3/MinIO/SFTP), which the caller
     * pairs with $config["storage"].
     */
    private function resolveSourcePath(string $path, array $config): ?string
    {
        // If already absolute and exists, return it
        if (file_exists($path)) {
            return $path;
        }

        // An external ("https://…") or protocol-relative ("//cdn/…") URL is never
        // ours to resolve. Bail out before touching a storage: existence checks on
        // a remote adapter are network round-trips, and asking S3 about every
        // third-party image URL in the content would pay for them on every render.
        if (str_starts_with($path, '//') || parse_url($path, PHP_URL_SCHEME) !== null) {
            return null;
        }

        // Extract storage from config
        $storage = $config["storage"] ?? null;

        // Resolve through flysystem
        if ($storage) {

            // Try both public and private storage (web paths are usually in .public)
            $storagesToTry = [$storage . '.public', $storage];

            foreach ($storagesToTry as $storageAttempt) {
                // Skip if storage doesn't exist
                if (!$this->flysystem->hasStorage($storageAttempt)) {
                    continue;
                }

                foreach ($this->getRelativePathCandidates($path, $storageAttempt) as $relativePath) {

                    // Ask the STORAGE whether the file is there, not the local
                    // filesystem. get() hands back a storage key on a remote
                    // adapter, never an openable local path, so the old
                    // file_exists() gate was false for every object that does
                    // exist on S3/MinIO — making this enhancer a silent no-op for
                    // remote-stored media, and leaving every editor image to be
                    // emitted raw instead of routed through /images.
                    if (!$this->flysystem->fileExists($relativePath, $storageAttempt)) {
                        continue;
                    }

                    if ($this->flysystem->isRemote($storageAttempt)) {
                        return $relativePath;
                    }

                    $absolutePath = $this->flysystem->get($relativePath, $storageAttempt);

                    if ($absolutePath && file_exists($absolutePath)) {
                        return $absolutePath;
                    }
                }
            }
        }

        // Fallback: try standard asset path resolution
        $assetPath = $this->flysystem->get($path);

        if ($assetPath && file_exists($assetPath)) {
            return $assetPath;
        }

        return null;
    }

    /**
     * Storage-relative paths to try for a given web path, most specific first.
     *
     * The happy path is a web path whose first segment matches the storage's own
     * public mount ("/wysiwyg/x.jpg" in "local.wysiwyg" -> "x.jpg").
     *
     * Content that predates a mount rename keeps the old prefix baked into it
     * for good — EditorJS stores the URL it was given at upload time, and
     * nothing rewrites it afterwards. "/media/x.jpg" was the mount before it
     * became "/wysiwyg/", so those blocks resolve against no storage at all and
     * are emitted verbatim into the page, where they 404 forever even though the
     * file itself was carried over intact. Falling back to the path minus its
     * leading segment lets any such renamed mount resolve again.
     *
     * @return string[]
     */
    private function getRelativePathCandidates(string $path, string $storage): array
    {
        if (!str_starts_with($path, '/')) {
            return [$path];
        }

        // Extract base path from storage name (e.g., "local.wysiwyg" -> "wysiwyg")
        $storageParts = explode('.', $storage);
        $basePrefix = $storageParts[count($storageParts) - 1]; // Get last part (skip .public)
        if ($basePrefix === 'public' && count($storageParts) > 1) {
            $basePrefix = $storageParts[count($storageParts) - 2]; // Use second-to-last if last is "public"
        }

        if (str_starts_with($path, '/' . $basePrefix . '/')) {
            return [substr($path, strlen('/' . $basePrefix . '/'))];
        }

        // Unknown (renamed) mount: try it as-is, then without its leading segment.
        // Only ever reached for paths that resolve to nothing today, so the extra
        // lookup never costs the happy path anything.
        $candidates = [$path];

        $withoutMount = strstr(ltrim($path, '/'), '/');
        if ($withoutMount !== false && ltrim($withoutMount, '/') !== '') {
            $candidates[] = ltrim($withoutMount, '/');
        }

        return $candidates;
    }
}
