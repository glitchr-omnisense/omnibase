<?php

namespace Base\View;

use Base\Service\MediaService;

/**
 * What a page shows of a stored picture through omnibase's media rule (plan "omnibase agnostic",
 * step F4): image(), |crop and the |thumbnail filters of PictureTwigExtension, which call it with
 * the template's context; Blade and a React page's props give theirs. In an e-mail being written
 * (a context's `email` being Symfony's WrappedTemplatedEmail) a picture is made at once and never
 * as WebP; a context's `warmup` is handed on.
 */
final class Pictures
{
    private const EMAIL = 'Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail';

    public function __construct(private readonly MediaService $media)
    {
    }

    /**
     * @param array<array-key, mixed>|string|null $path    a path, or several
     * @param array<string, mixed>                $config
     * @param array<array-key, mixed>             $filters
     * @param array<string, mixed>                $context the view's (its `email`, its `warmup`)
     *
     * @return array<array-key, mixed>|string|null
     */
    public function image(array|string|null $path, array $config = [], array $filters = [], array $context = []): array|string|null
    {
        $config['local_cache'] ??= true;

        return $this->media->image($path, self::config($context, $config), $filters);
    }

    /**
     * @param array<array-key, mixed>|string|null $path
     * @param array<string, mixed>                $config
     * @param array<array-key, mixed>             $filters
     * @param array<string, mixed>                $context
     *
     * @return array<array-key, mixed>|string|null
     */
    public function crop(array|string|null $path, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null, string $position = 'leftop', array $config = [], array $filters = [], array $context = []): array|string|null
    {
        $config['local_cache'] ??= true;

        return $this->media->crop($path, $x, $y, $width, $height, $position, self::config($context, $config), $filters);
    }

    /**
     * A picture's thumbnail at a size; `mode` in the config (ImageInterface::THUMBNAIL_INSET,
     * OUTBOUND, FLAG_NOCLONE, FLAG_UPSCALE) as the |thumbnail_* filters give it.
     *
     * @param array<array-key, mixed>|string|null $path
     * @param array<string, mixed>                $config
     * @param array<array-key, mixed>             $filters
     * @param array<string, mixed>                $context
     *
     * @return array<array-key, mixed>|string|null
     */
    public function thumbnail(array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = [], array $context = []): array|string|null
    {
        return $this->media->thumbnail($path, $width, $height, self::config($context, $config), $filters);
    }

    /**
     * The config of a picture in a view's context: its warmup handed on; in an e-mail, made at
     * once and never as WebP.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function config(array $context, array $config): array
    {
        if (\array_key_exists('warmup', $context)) {
            $config['warmup'] = $context['warmup'];
        }

        if (is_a($context['email'] ?? null, self::EMAIL)) {
            $config['warmup'] = true;
            $config['webp'] = false;
        }

        return $config;
    }
}
