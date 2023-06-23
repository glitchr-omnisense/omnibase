<?php

namespace Base\Twig\Extension;

use Base\Controller\UX\MediaController;
use Base\Routing\AdvancedRouter;
use Base\Routing\RouterInterface;
use Base\Service\FileService;
use Base\Service\IconProvider;
use Base\Service\MediaService;
use Base\Service\Model\LinkableInterface;
use Base\Service\Obfuscator;
use Base\Twig\Environment;
use Imagine\Image\ImageInterface;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 *
 */
final class MediaTwigExtension extends AbstractExtension
{
    protected RouterInterface $router;

    protected MediaController $mediaController;

    /**
     * @var MediaService
     */
    protected $mediaService;

    protected string $projectDir;

    public function __construct(RouterInterface $router, MediaService $mediaService, MediaController $mediaController, string $projectDir)
    {
        $this->projectDir = $projectDir;

        $this->router = $router;
        $this->mediaController = $mediaController;

        $this->mediaService = $mediaService;
        $this->mediaService->setController($mediaController);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('embed', [$this, 'embed'], ['needs_environment' => true, 'needs_context' => true]),
            new TwigFunction('embed_base64', [MediaService::class, 'imageBase64']),
            new TwigFunction('url', [$this, 'url'], ['needs_context' => true]),
            new TwigFunction('imageset', [MediaService::class, 'imageSet'], ['is_safe' => ['all']]),
            new TwigFunction('image', [$this, 'image'], ['needs_context' => true]),
            new TwigFunction('video', [MediaService::class, 'video'], ['is_safe' => ['all']]),
            new TwigFunction('audio', [MediaService::class, 'audio'], ['is_safe' => ['all']]),

            new TwigFunction('asset', [AdvancedRouter::class, 'getAssetUrl']),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('ondisk', [$this, 'onDisk']),
        ];
    }

    public function getFilters(): array
    {
        return
            [
                new TwigFilter('soundify', [MediaService::class, 'soundify'], ['is_safe' => ['all']]),
                new TwigFilter('vidify', [MediaService::class, 'vidify'], ['is_safe' => ['all']]),
                new TwigFilter('iconify', [IconProvider::class, 'iconify'], ['is_safe' => ['all']]),
                new TwigFilter('imagify', [MediaService::class, 'imagify'], ['is_safe' => ['all']]),
                new TwigFilter('urlify', [$this, 'urlify'], ['is_safe' => ['all']]),
                new TwigFilter('linkify', [$this, 'linkify'], ['is_safe' => ['all']]),

                new TwigFilter('public', [FileService::class, 'public']),
                new TwigFilter('downloadable', [FileService::class, 'downloadable']),
                new TwigFilter('mimetype', [FileService::class, 'getMimeType']),
                new TwigFilter('extensions', [FileService::class, 'getExtensions']),

                new TwigFilter('inline_css_email', [$this, 'inline_css_email'], ['needs_context' => true, 'is_safe' => ['all']]),
                new TwigFilter('embed', [$this, 'embed'], ['needs_environment' => true, 'needs_context' => true]),
                new TwigFilter('embed_base64', [MediaService::class, 'imageBase64']),
                new TwigFilter('url', [$this, 'url'], ['needs_context' => true]),

                new TwigFilter('asset', [AdvancedRouter::class, 'getAssetUrl']),
                new TwigFilter('filesize', [FileService::class, 'filesize']),
                new TwigFilter('obfuscate', [Obfuscator::class, 'encode']),
                new TwigFilter('obfuscate_file', [FileService::class, 'obfuscate']),
                new TwigFilter('obfuscate_image', [MediaService::class, 'obfuscate']),
                new TwigFilter('lightbox', [MediaService::class, 'lightbox'], ['is_safe' => ['all']]),

                new TwigFilter('crop', [$this, 'imageCrop'], ['needs_context' => true]),

                new TwigFilter('thumbnail', [$this, 'thumbnail'], ['needs_context' => true]),
                new TwigFilter('thumbnail_inset   ', [$this, 'thumbnailInset   '], ['needs_context' => true]),
                new TwigFilter('thumbnail_outbound', [$this, 'thumbnailOutbound'], ['needs_context' => true]),
                new TwigFilter('thumbnail_noclone ', [$this, 'thumbnailNoclone '], ['needs_context' => true]),
                new TwigFilter('thumbnail_upscale ', [$this, 'thumbnailUpscale '], ['needs_context' => true]),
            ];
    }

    public function onDisk(string $file): bool
    {
        return file_exists(sanitize_url($this->projectDir . '/public/' . $file));
    }

    public function image(array $context, array|string|null $path, array $config = [], array $filters = []): array|string|null
    {
        $config['local_cache'] ??= true;
        if (array_key_exists('warmup', $context)) {
            $config['warmup'] = $context['warmup'];
        }

        $email = $context['email'] ?? null;
        if ($email instanceof WrappedTemplatedEmail) {
            $config['warmup'] = true;
            $config['webp'] = false;
        }

        return $this->mediaService->image($path, $config, $filters);
    }

    public function imageCrop(array $context, array|string|null $path, int $x = 0, int $y = 0, ?int $width = null, ?int $height = null, string $position = 'leftop', array $config = [], array $filters = []): array|string|null
    {
        $config['local_cache'] ??= true;
        if (array_key_exists('warmup', $context)) {
            $config['warmup'] = $context['warmup'];
        }

        $email = $context['email'] ?? null;
        if ($email instanceof WrappedTemplatedEmail) {
            $config['warmup'] = true;
            $config['webp'] = false;
        }

        return $this->mediaService->crop($path, $x, $y, $width, $height, $position, $config, $filters);
    }

    public function thumbnailInset(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($context, $path, $width, $height, array_merge($config, ['mode' => ImageInterface::THUMBNAIL_INSET]), $filters);
    }

    public function thumbnailOutbound(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($context, $path, $width, $height, array_merge($config, ['mode' => ImageInterface::THUMBNAIL_OUTBOUND]), $filters);
    }

    public function thumbnailNoclone(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($context, $path, $width, $height, array_merge($config, ['mode' => ImageInterface::THUMBNAIL_FLAG_NOCLONE]), $filters);
    }

    public function thumbnailUpscale(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        return $this->thumbnail($context, $path, $width, $height, array_merge($config, ['mode' => ImageInterface::THUMBNAIL_FLAG_UPSCALE]), $filters);
    }

    public function thumbnail(array $context, array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], array $filters = []): array|string|null
    {
        if (array_key_exists('warmup', $context)) {
            $config['warmup'] = $context['warmup'];
        }

        $email = $context['email'] ?? null;
        if ($email instanceof WrappedTemplatedEmail) {
            $config['warmup'] = true;
            $config['webp'] = false;
        }

        return $this->mediaService->thumbnail($path, $width, $height, $config, $filters);
    }

    /**
     * @param LinkableInterface|string|null $urlOrPath
     * @param string|null $label
     * @param array $attributes
     * @return string
     */
    public function urlify(LinkableInterface|string|null $urlOrPath, ?string $label = null, array $attributes = [])
    {
        $url = $urlOrPath;
        $label = $label ?? $urlOrPath;
        if ($urlOrPath instanceof LinkableInterface) {
            $url = $urlOrPath->__toLink();
            $label = $label ?? $urlOrPath->__toString();
        }

        if ($this->router->getUrl() == $this->router->getAssetUrl($urlOrPath)) {
            $attributes['class'] = trim(($attributes['class'] ?? '') . ' highlight');
        }

        if (!$url) {
            return '';
        }

        return "<a href='" . $url . "' " . html_attributes($attributes) . '>' . $label . '</a>';
    }

    /**
     * @param mixed $urlOrPath
     * @return string|null
     */
    public function linkify(mixed $urlOrLinkableObject)
    {
        $url = $urlOrLinkableObject;
        if (is_object($urlOrLinkableObject)) {
            
            if (!$urlOrLinkableObject instanceof LinkableInterface) 
                throw new \LogicException("Object \"".get_class($urlOrLinkableObject)."\" received doesn't implement \"".LinkableInterface::class."\"");
            
            $url = $urlOrLinkableObject->__toLink();
        }

        return is_object($url) ? null : (is_string($url) ? $url : null);
    }

    /**
     * @param array $context
     * @param string|null $name
     * @param array $parameters
     * @param int $referenceType
     * @return string|null
     */
    public function url(array $context, ?string $name, array $parameters = [], int $referenceType = AdvancedRouter::ABSOLUTE_URL)
    {
        if (null == $name) {
            return $name;
        }

        $email = $context['email'] ?? null;
        $referenceType = $email instanceof WrappedTemplatedEmail ? AdvancedRouter::ABSOLUTE_URL : $referenceType;

        return trim($this->router->getUrl($name, $parameters, $referenceType));
    }

    public function inline_css_email(array $context, string $body, string ...$css): string
    {
        static $inliner;
        if (null === $inliner) {
            $inliner = new CssToInlineStyles();
        }

        $email = $context['email'] ?? null;

        return $email ? $inliner->convert($body, implode("\n", $css)) : $body;
    }

    /**
     * @param Environment $twig
     * @param array $context
     * @param string $src
     * @param array $options
     * @return string|null
     */
    public function embed(Environment $twig, array $context, string $src, array $options = [])
    {
        if (!$src) {
            return $src;
        }

        if (!str_starts_with($src, '@')) {
            $src = '@Public/' . str_lstrip($src, [$this->projectDir . '/public', '/']);
        }

        $path = $src;
        $url = explode('/', $src);
        try {
            $path = $twig->getLoader()->getSourceContext($src)->getPath();
            $contentType = mime_content_type($twig->getLoader()->getSourceContext($src)->getPath());

            $url = explode('/', $twig->getLoader()->getSourceContext($src)->getName());
        } catch (LoaderError $e) {
            return $src;
        }

        $prefix = str_rstrip($path, [implode('/', tail($url)), '/']);
        $email = $options['email'] ?? $context['email'] ?? null;

        return $email instanceof WrappedTemplatedEmail ? $email->image($src, $contentType) : str_lstrip($path, [
            $prefix,
            $this->projectDir . '/public',
            $this->projectDir,
        ]);
    }
}
