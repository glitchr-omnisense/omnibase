<?php

namespace Base\Twig\Extension;

use Base\Service\Model\LinkableInterface;
use Base\Routing\RouterInterface;
use Base\Service\FileService;
use Base\Service\IconProvider;
use Base\Service\ImageService;
use Base\Twig\Environment;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class FileTwigExtension extends AbstractExtension
{
    public function __construct(RouterInterface $router)
    {
        $this->router   = $router;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('iconify', [IconProvider::class, 'iconify'], ["is_safe" => ['all']]),
            new TwigFunction('urlify',  [$this,               'urlify' ], ["is_safe" => ['all']]),
            new TwigFunction('linkify',  [$this,              'linkify' ], ["is_safe" => ['all']]),
            new TwigFunction('asset',   [$this,               'asset']),
            new TwigFunction('image',   [$this, 'image'], ['needs_environment' => true, 'needs_context' => true])
        ];
    }

    public function getFilters() : array
    {
        return
        [
            new TwigFilter('iconify',        [IconProvider::class, 'iconify'], ["is_safe" => ['all']]),
            new TwigFilter('imagify',        [ImageService::class, 'imagify'], ["is_safe" => ['all']]),
            new TwigFilter('urlify',         [$this, 'urlify' ], ["is_safe" => ['all']]),
            new TwigFilter('linkify',        [$this, 'linkify' ], ["is_safe" => ['all']]),

            new TwigFilter('public',         [FileService::class, 'public']),
            new TwigFilter('downloadable',   [FileService::class, 'downloadable']),
            new TwigFilter('mimetype',       [FileService::class, 'getMimeType']),
            new TwigFilter('extensions',     [FileService::class, 'getExtensions']),

            new TwigFilter('obfuscate',      [FileService::class, 'obfuscate']),
            new TwigFilter('imagine',        [ImageService::class, 'imagine']),
            new TwigFilter('webp',           [ImageService::class, 'webp']),
            new TwigFilter('crop',           [ImageService::class, 'crop']),
            new TwigFilter('image',          [ImageService::class, 'image']),
            new TwigFilter('lightbox',       [ImageService::class, 'lightbox'], ["is_safe" => ['all']]),
            new TwigFilter('url',            [$this, 'url']),
            new TwigFilter('filesize',       [$this, 'filesize']),

            new TwigFilter('thumbnail',          [ImageService::class, 'thumbnail']),
            new TwigFilter('thumbnail_inset   ', [ImageService::class, 'thumbnail_inset   ']),
            new TwigFilter('thumbnail_outbound', [ImageService::class, 'thumbnail_outbound']),
            new TwigFilter('thumbnail_noclone ', [ImageService::class, 'thumbnail_noclone ']),
            new TwigFilter('thumbnail_upscale ', [ImageService::class, 'thumbnail_upscale ']),
        ];
    }

    public function urlify(LinkableInterface|string $urlOrPath, ?string $label = null, array $attributes = [])
    {
        $url   = $urlOrPath;
        $label = $label ?? $urlOrPath;
        if($urlOrPath instanceof LinkableInterface) {
            $url   = $urlOrPath->__toLink();
            $label = $label ?? $urlOrPath->__toString();
        }

        if($this->router->getUrl() == $this->router->getAssetUrl($urlOrPath))
            $attributes["class"] = trim(($attributes["class"] ?? "")." highlight");

        return "<a href='".$url."' ".html_attributes($attributes).">".$label."</a>";
    }

    public function linkify(LinkableInterface|string $urlOrPath)
    {
        $url   = $urlOrPath;
        if($urlOrPath instanceof LinkableInterface)
            $url   = $urlOrPath->__toLink();

        return $url;
    }

    public function asset($path, ?string $packageName = null) {

        if($path === false || $path === null) return null;
        return $this->router->getAssetUrl($path, $packageName);
    }

    public function image(Environment $env, array $context, $src)
    {
        if(!$src) return $src;
        if(is_array($src)) return array_map(fn($s) => $this->image($s, $context, $env), $src);

        $email = $context["email"] ?? null;
        if( $email instanceof WrappedTemplatedEmail )
            return $email->image($src);

        // Context and public path
        if(str_starts_with($src, "/")) $src = "@Public".$src;
        try { $src = $env->getLoader()->getSourceContext($src)->getPath(); }
        catch(LoaderError $e) { throw new NotFoundResourceException("Image \"$src\" not found."); }

        if (substr($src, 0, strlen($this->projectDir)) == $this->projectDir)
            $src = substr($src, strlen($this->projectDir));

        return $src;
    }

    public function filesize($size, array $unitPrefix = DECIMAL_PREFIX): string
    {
        return byte2str($size, $unitPrefix);
    }


    public function url(?string $url): ?string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);

        if(!array_key_exists("schema", $parseUrl)) {

            $path = $url;

            $https      = $_SERVER['HTTPS']       ?? $this->baseService->getSettingBag()->scheme();
            $serverName = $_SERVER['SERVER_NAME'] ?? $this->baseService->getSettingBag()->domain();
            $baseDir    = $_SERVER['BASE']        ?? $_SERVER["CONTEXT_PREFIX"] ?? $this->baseService->getSettingBag()->base_dir();
            $baseDir    = "/".trim($baseDir, "/");

            if(str_starts_with($path, "http://") || str_starts_with($path, "https://")) $domain = "";
            else $domain = ($https ? "https://" : "http://") . $serverName;

            if (!empty($domain)) $join = str_starts_with($path, "/") ? "" : $baseDir;
            else $join = "";

            $url = $domain . $join . $path;
        }

        return $url;
    }

}