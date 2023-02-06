<?php

namespace Base\Twig\Extension;

use Base\Routing\AdvancedRouter;
use Base\Service\Model\LinkableInterface;
use Base\Routing\RouterInterface;
use Base\Service\FileService;
use Base\Service\IconProvider;
use Base\Service\ImageService;
use Base\Service\Obfuscator;
use Base\Twig\Environment;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class FileTwigExtension extends AbstractExtension
{
    /**
     * @var Router
     */
    protected $router;

    /** @var string */
    protected $projectDir;

    public function __construct(RouterInterface $router, string $projectDir)
    {
        $this->router   = $router;
        $this->projectDir = $projectDir;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('urlify',  [$this, 'urlify' ], ["is_safe" => ['all']]),
            new TwigFunction('linkify', [$this, 'linkify' ], ["is_safe" => ['all']]),
            new TwigFunction('embed',   [$this, 'embed'], ['needs_environment' => true, 'needs_context' => true]),
            new TwigFunction('url',     [$this, 'url'], ['needs_context' => true]),

            new TwigFunction('iconify',  [IconProvider::class,   'iconify'], ["is_safe" => ['all']]),
            new TwigFunction('imagify',  [ImageService::class, 'imagify'], ["is_safe" => ['all']]),
            new TwigFunction('imageset', [ImageService::class, 'imageSet'], ["is_safe" => ['all']]),
            new TwigFunction('urlify',   [$this, 'urlify' ], ["is_safe" => ['all']]),
            new TwigFunction('linkify',  [$this, 'linkify' ], ["is_safe" => ['all']]),
            new TwigFunction('lightbox', [ImageService::class,   'lightbox'], ["is_safe" => ['all']]),
            new TwigFunction('image',    [ImageService::class,   'image']),

            new TwigFunction('asset',   [AdvancedRouter::class, 'getAssetUrl']),
        ];
    }

    public function getFilters() : array
    {
        return
        [
            new TwigFilter('iconify',        [IconProvider::class, 'iconify'], ["is_safe" => ['all']]),
            new TwigFilter('imagify',        [ImageService::class, 'imagify'], ["is_safe" => ['all']]),
            new TwigFilter('imageset',       [ImageService::class, 'imageSet'], ["is_safe" => ['all']]),
            new TwigFilter('urlify',         [$this, 'urlify' ], ["is_safe" => ['all']]),
            new TwigFilter('linkify',        [$this, 'linkify' ], ["is_safe" => ['all']]),

            new TwigFilter('public',         [FileService::class, 'public']),
            new TwigFilter('downloadable',   [FileService::class, 'downloadable']),
            new TwigFilter('mimetype',       [FileService::class, 'getMimeType']),
            new TwigFilter('extensions',     [FileService::class, 'getExtensions']),

            new TwigFilter('inline_css_email', [$this, 'inline_css_email'], ['needs_context' => true, "is_safe" => ['all']]),
            new TwigFilter('embed',            [$this, 'embed'], ['needs_environment' => true, 'needs_context' => true]),
            new TwigFilter('url',              [$this, 'url'], ['needs_context' => true]),

            new TwigFilter('asset',          [AdvancedRouter::class, 'getAssetUrl']),
            new TwigFilter('filesize',       [FileService::class,    'filesize']),
            new TwigFilter('obfuscate',      [Obfuscator::class,     'encode']),
            new TwigFilter('obfuscate_file', [FileService::class,    'obfuscate']),
            new TwigFilter('obfuscate_image',[ImageService::class,   'obfuscate']),
            new TwigFilter('imagine',        [ImageService::class,   'imagine']),
            new TwigFilter('webp',           [ImageService::class,   'webp']),
            new TwigFilter('crop',           [ImageService::class,   'crop']),
            new TwigFilter('image',          [ImageService::class,   'image']),
            new TwigFilter('lightbox',       [ImageService::class,   'lightbox'], ["is_safe" => ['all']]),

            new TwigFilter('thumbnail',          [ImageService::class, 'thumbnail']),
            new TwigFilter('thumbnail_inset   ', [ImageService::class, 'thumbnail_inset   ']),
            new TwigFilter('thumbnail_outbound', [ImageService::class, 'thumbnail_outbound']),
            new TwigFilter('thumbnail_noclone ', [ImageService::class, 'thumbnail_noclone ']),
            new TwigFilter('thumbnail_upscale ', [ImageService::class, 'thumbnail_upscale ']),
        ];
    }

    public function urlify(LinkableInterface|string|null $urlOrPath, ?string $label = null, array $attributes = [])
    {
        
        $url   = $urlOrPath;
        $label = $label ?? $urlOrPath;
        if($urlOrPath instanceof LinkableInterface) {
            $url   = $urlOrPath->__toLink();
            $label = $label ?? $urlOrPath->__toString();
        }
        
        if($this->router->getUrl() == $this->router->getAssetUrl($urlOrPath))
        $attributes["class"] = trim(($attributes["class"] ?? "")." highlight");

        if(!$url) return "";

        return "<a href='".$url."' ".html_attributes($attributes).">".$label."</a>";
    }

    public function linkify(mixed $urlOrPath)
    {
        $url   = $urlOrPath;
        if($urlOrPath instanceof LinkableInterface)
            $url   = $urlOrPath->__toLink();

        return is_object($url) ? null : (is_string($url) ? $url : null);
    }

    public function url(array $context, ?string $name, array $parameters = [], int $referenceType = AdvancedRouter::ABSOLUTE_PATH)
    {
        if($name == null) return $name;

        $email = $context["email"] ?? null;
        $referenceType = $email instanceof WrappedTemplatedEmail ? AdvancedRouter::ABSOLUTE_URL : $referenceType;

        return trim($this->router->getUrl($name, $parameters, $referenceType));
    }

    public function inline_css_email(array $context, string $body, string ...$css): string
    {
        static $inliner;
        if (null === $inliner) {
            $inliner = new CssToInlineStyles();
        }
    
        $email = $context["email"] ?? null;
        return $email ? $inliner->convert($body, implode("\n", $css)) : $body;
    }

    public function embed(Environment $twig, array $context, string $src)
    {
        if(!$src) return $src;

        if(!str_starts_with($src, "@")) $src = "@Public/".str_lstrip($src, [$this->projectDir."/public", "/"]);

        try {
            $path = $twig->getLoader()->getSourceContext($src)->getPath();
            $contentType = mime_content_type($path);
        }
        catch ( LoaderError $e) { throw $e; }

        $email = $context["email"] ?? null;

        return $email instanceof WrappedTemplatedEmail ? $email->image($src, $contentType) : str_lstrip($path, [
            $this->projectDir."/public",
            $this->projectDir."/data",
            $this->projectDir
        ]);
    }
}
