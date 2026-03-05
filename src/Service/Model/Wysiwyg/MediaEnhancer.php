<?php

namespace Base\Service\Model\Wysiwyg;

use Base\Imagine\FilterInterface;
use Base\Service\FlysystemInterface;
use Base\Service\MediaServiceInterface;
use Base\Traits\BaseTrait;

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

    public function __construct(MediaServiceInterface $mediaService, FlysystemInterface $flysystem)
    {
        $this->mediaService = $mediaService;
        $this->flysystem = $flysystem;
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

            // Verify file exists before processing
            $absolutePath = $this->resolveAbsolutePath($entry, $config);

            if (!$absolutePath || !file_exists($absolutePath)) {
                // Path not found - keep original path unchanged
                continue;
            }

            // ENCODE ONCE: Generate obfuscated URL through media service
            // Pass the ABSOLUTE filesystem path (not web-relative) so it can be properly cached
            $obfuscatedUrl = $this->mediaService->image($absolutePath, $config, $filters);

            // If encoding succeeded, use the obfuscated URL
            // This ensures ALL paths end up as /images/XX/XX/.../image.webp
            if ($obfuscatedUrl && is_string($obfuscatedUrl)) {
                $entry = $obfuscatedUrl;
            }
        }

        return is_array($strOrArray) ? $array : first($array);
    }

    private function resolveAbsolutePath(string $path, array $config): ?string
    {
        // If already absolute and exists, return it
        if (file_exists($path)) {
            return $path;
        }

        // Extract storage from config
        $storage = $config["storage"] ?? null;

        // Build absolute path using flysystem
        if ($storage) {

            // Try both public and private storage (web paths are usually in .public)
            $storagesToTry = [$storage . '.public', $storage];

            foreach ($storagesToTry as $storageAttempt) {
                // Skip if storage doesn't exist
                if (!$this->flysystem->hasStorage($storageAttempt)) {
                    continue;
                }

                // Strip the web prefix to get storage-relative path
                // e.g., /wysiwyg/a4/fe/... -> a4/fe/... for local.wysiwyg storage
                $relativePath = $path;
                if (str_starts_with($path, '/')) {

                    // Extract base path from storage name (e.g., "local.wysiwyg" -> "wysiwyg")
                    $storageParts = explode('.', $storageAttempt);
                    $basePrefix = $storageParts[count($storageParts) - 1]; // Get last part (skip .public)
                    if ($basePrefix === 'public' && count($storageParts) > 1) {
                        $basePrefix = $storageParts[count($storageParts) - 2]; // Use second-to-last if last is "public"
                    }

                    if (str_starts_with($path, '/' . $basePrefix . '/')) {
                        $relativePath = substr($path, strlen('/' . $basePrefix . '/'));
                    }
                }

                $absolutePath = $this->flysystem->get($relativePath, $storageAttempt);

                if ($absolutePath && file_exists($absolutePath)) {
                    return $absolutePath;
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
}
