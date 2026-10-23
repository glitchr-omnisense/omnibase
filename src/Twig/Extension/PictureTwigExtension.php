<?php

namespace Base\Twig\Extension;

use Base\Service\FileService;
use Base\Service\MediaService;
use Imagine\Image\ImageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * What a template shows of a stored file through omnibase's media rule
 * (MediaService, Base\Service\FileService): `image()` and `|thumbnail` (the
 * image route's obfuscated address of a picture, at a size, in the format
 * the browser takes), `|crop`, `|soundify` and `|vidify` (an audio or video
 * player over the files' addresses), `|imagify`, `imageset()`, `|mimetype`,
 * `|extensions`, `|filesize`, `|public`, `|downloadable`, `|embed_base64`,
 * `|obfuscate_image`, `|obfuscate_file`, `|lightbox`.
 *
 * Framework-free (audit "lesringostars face au master plan", R24): Symfony
 * registers it with its MediaService and FileService services, Laravel
 * (omnibridge/laravel, omnibridge.media on) with the MediaService it builds
 * on its own router - the same addresses from the same code. They were
 * MediaTwigExtension's, under the same names; what needs Symfony's router
 * (url(), asset(), embed(), |inline_css_email...) stays there.
 *
 * In an e-mail (a context's `email` being Symfony's WrappedTemplatedEmail) a
 * picture is made at once and never as WebP, as before; a context's `warmup`
 * is handed on.
 */
final class PictureTwigExtension extends AbstractExtension
{
    private const EMAIL = 'Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail';

    private FileService $files;

    public function __construct(private readonly MediaService $media, ?FileService $files = null)
    {
        $this->files = $files ?? $media;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('embed_base64', [$this->media, 'imageBase64']),
            new TwigFunction('imageset', [$this->media, 'imageSet'], ['is_safe' => ['all']]),
            new TwigFunction('image', [$this, 'image'], ['needs_context' => true]),
            new TwigFunction('video', [$this->media, 'video'], ['is_safe' => ['all']]),
            new TwigFunction('audio', [$this->media, 'audio'], ['is_safe' => ['all']]),
        ];
    }

    public function getFilters(): array
    {
        return
            [
                new TwigFilter('soundify', [$this->media, 'soundify'], ['is_safe' => ['all']]),
                new TwigFilter('vidify', [$this->media, 'vidify'], ['is_safe' => ['all']]),
                new TwigFilter('imagify', [$this->media, 'imagify'], ['is_safe' => ['all']]),

                new TwigFilter('public', [$this->files, 'public']),
                new TwigFilter('downloadable', [$this->files, 'downloadable']),
                new TwigFilter('mimetype', [$this->files, 'getMimeType']),
                new TwigFilter('extensions', [$this->files, 'getExtensions']),

                new TwigFilter('embed_base64', [$this->media, 'imageBase64']),
                new TwigFilter('filesize', [$this->files, 'filesize']),
                new TwigFilter('obfuscate_file', [$this->files, 'obfuscate']),
                new TwigFilter('obfuscate_image', [$this->media, 'obfuscate']),
                new TwigFilter('lightbox', [$this->media, 'lightbox'], ['is_safe' => ['all']]),

                new TwigFilter('crop', [$this, 'imageCrop'], ['needs_context' => true]),

                new TwigFilter('thumbnail', [$this, 'thumbnail'], ['needs_context' => true]),
                new TwigFilter('thumbnail_inset', [$this, 'thumbnailInset'], ['needs_context' => true]),
                new TwigFilter('thumbnail_outbound', [$this, 'thumbnailOutbound'], ['needs_context' => true]),
                new TwigFilter('thumbnail_noclone', [$this, 'thumbnailNoclone'], ['needs_context' => true]),
                new TwigFilter('thumbnail_upscale', [$this, 'thumbnailUpscale'], ['needs_context' => true]),
            ];
    }

    /**
     * @param array<string, mixed>              $context the template's
     * @param array<array-key, mixed>|string|null $path    a path, or several
     * @param array<string, mixed>              $config
     * @param array<array-key, mixed>           $filters
     *
     * @return array<array-key, mixed>|string|null
     */
    public function image(array $context, array|string|null $path, array $config = [], array $filters = []): array|string|null
    {
        $config['local_cache'] ??= true;

        return $this->media->image($path, self::config($context, $config), $filters);
    }

    /**
     * @param array<string, mixed>              $context the template's
     * @param array<array-key, mixed>|string|null $path    a path, or several
     * @param array<string, mixed>              $config
     * @param array<array-key, mixed>           $filters
     *
     * @return array<array-key, mixed>|string|null
     */
    public function imageCrop(array $context, array|string|null $path, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null, string $position = 'leftop', array $config = [], array $filters = []): array|string|null
    {
        $config['local_cache'] ??= true;

        return $this->media->crop($path, $x, $y, $width, $height, $position, self::config($context, $config), $filters);
    }

    /**
     * @param array<string, mixed>              $context the template's
     * @param array<array-key, mixed>|string|null $path    a path, or several
     * @param array<string, mixed>              $config
     * @param array<array-key, mixed>           $filters
     *
     * @return array<array-key, mixed>|string|null
     */
    public function thumbnailInset(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($context, $path, $width, $height, array_merge($config, ['mode' => ImageInterface::THUMBNAIL_INSET]), $filters);
    }

    /**
     * @param array<string, mixed>              $context the template's
     * @param array<array-key, mixed>|string|null $path    a path, or several
     * @param array<string, mixed>              $config
     * @param array<array-key, mixed>           $filters
     *
     * @return array<array-key, mixed>|string|null
     */
    public function thumbnailOutbound(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($context, $path, $width, $height, array_merge($config, ['mode' => ImageInterface::THUMBNAIL_OUTBOUND]), $filters);
    }

    /**
     * @param array<string, mixed>              $context the template's
     * @param array<array-key, mixed>|string|null $path    a path, or several
     * @param array<string, mixed>              $config
     * @param array<array-key, mixed>           $filters
     *
     * @return array<array-key, mixed>|string|null
     */
    public function thumbnailNoclone(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($context, $path, $width, $height, array_merge($config, ['mode' => ImageInterface::THUMBNAIL_FLAG_NOCLONE]), $filters);
    }

    /**
     * @param array<string, mixed>              $context the template's
     * @param array<array-key, mixed>|string|null $path    a path, or several
     * @param array<string, mixed>              $config
     * @param array<array-key, mixed>           $filters
     *
     * @return array<array-key, mixed>|string|null
     */
    public function thumbnailUpscale(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($context, $path, $width, $height, array_merge($config, ['mode' => ImageInterface::THUMBNAIL_FLAG_UPSCALE]), $filters);
    }

    /**
     * @param array<string, mixed>              $context the template's
     * @param array<array-key, mixed>|string|null $path    a path, or several
     * @param array<string, mixed>              $config
     * @param array<array-key, mixed>           $filters
     *
     * @return array<array-key, mixed>|string|null
     */
    public function thumbnail(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->media->thumbnail($path, $width, $height, self::config($context, $config), $filters);
    }

    /**
     * A context's warmup handed on; in an e-mail, made at once and never as WebP.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function config(array $context, array $config): array
    {
        if (array_key_exists('warmup', $context)) {
            $config['warmup'] = $context['warmup'];
        }

        $email = $context['email'] ?? null;
        if (is_a($email, self::EMAIL)) {
            $config['warmup'] = true;
            $config['webp'] = false;
        }

        return $config;
    }
}
