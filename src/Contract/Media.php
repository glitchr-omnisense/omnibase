<?php

namespace Base\Contract;

use Base\Imagine\FilterInterface;

/**
 * The image route as an entity builds its addresses (plan "omnibase
 * agnostique", C4b): a stored image is served by omnibase's route ux_image
 * (or ux_imageExtension) with its path and how to serve it - a crop, a
 * thumbnail, the filters, a salt - obfuscated in one `data` parameter, and the
 * extension it is served with. Image::__toLink() and ImageCrop::__toLink()
 * ask it instead of BaseTrait's MediaService.
 *
 * The Fondation's own contract, reached by Runtime::media(). Under Symfony,
 * Base\Bridge\Symfony\ServiceMedia: MediaService's answers. A host without
 * the framework that serves omnibase's images gives its own (the obfuscation
 * is Base\Service\Obfuscator's, a class of the Fondation).
 */
interface Media
{
    /**
     * The extension a stored file is served with, from its type ("jpg",
     * "png"); null when none is known.
     */
    public function extension(?string $path): ?string;

    /**
     * The image route's `data` for a stored file: its path, the config
     * (identifier, salt, webp...) and the filters, obfuscated; null for no
     * path.
     *
     * @param array<string, mixed>  $config
     * @param list<FilterInterface> $filters
     */
    public function obfuscate(?string $path, array $config = [], array $filters = []): ?string;

    /**
     * The address of an image route (ux_image, ux_imageExtension) for a
     * stored file, its `data` made from the config (reference_type,
     * identifier, salt, filters...); null for no path.
     *
     * @param array<string, mixed> $parameters the route's other parameters (extension...)
     * @param array<string, mixed> $config
     */
    public function link(string $route, array $parameters, ?string $path, array $config = []): ?string;
}
