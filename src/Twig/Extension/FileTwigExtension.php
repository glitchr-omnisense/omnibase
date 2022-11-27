<?php

namespace Base\Twig\Extension;

use Base\Routing\AdvancedRouter;
use Base\Service\Model\LinkableInterface;
use Base\Routing\RouterInterface;
use Base\Service\FileService;
use Base\Service\IconProvider;
use Base\Service\ImageService;
use Base\Twig\Environment;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Symfony\WebpackEncoreBundle\Exception\EntrypointNotFoundException;
use Symfony\WebpackEncoreBundle\Exception\UndefinedBuildException;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class FileTwigExtension extends AbstractExtension
{
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

            new TwigFunction('iconify', [IconProvider::class,   'iconify'], ["is_safe" => ['all']]),
            new TwigFunction('asset',   [AdvancedRouter::class, 'getAssetUrl']),

            new TwigFunction('encore_custom_entry_link_tags',   [$this, 'getEncoreCustomEntryLinkTags'], ["is_safe" => ['all'], 'needs_environment' => true]),
            new TwigFunction('encore_custom_entry_script_tags',   [$this, 'getEncoreCustomEntryScriptTags'], ["is_safe" => ['all'], 'needs_environment' => true])
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

            new TwigFilter('embed',          [$this, 'embed'], ['needs_environment' => true, 'needs_context' => true]),
            new TwigFilter('url',            [$this, 'url'], ['needs_context' => true]),
            new TwigFilter('asset',          [AdvancedRouter::class, 'getAssetUrl']),
            new TwigFilter('filesize',       [FileService::class,    'filesize']),
            new TwigFilter('obfuscate',      [FileService::class,    'obfuscate']),
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

    public function getEncoreCustomEntryScriptTags(Environment $env, string|array $entry) : array
    {
        $entryName = is_array($entry) ? $entry["value"] ?? null : $entry;
        if($entryName == null) return [];
       
        $entryPoints = $env->getEncoreEntrypoints();
        foreach($entryPoints as $entryPoint) {
            
            try { $jsFiles = $entryPoint->getJavaScriptFiles($entryName); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

            if($jsFiles) return array_map(fn($e) => ["value" => $e], $jsFiles);
        }

        return [];
    }

    public function getEncoreCustomEntryLinkTags(Environment $env, string|array $entry) : array
    {
        $entryName = is_array($entry) ? $entry["value"] ?? null : $entry;
        if($entryName == null) return [];
       
        $entryPoints = $env->getEncoreEntrypoints();
        foreach($entryPoints as $entryPoint) {
            
            try { $cssFiles = $entryPoint->getCssFiles($entryName); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

            if($cssFiles) return array_map(fn($e) => ["value" => $e], $cssFiles);
        }

        return [];
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
            $this->projectDir, 
            $this->projectDir."/public",
            $this->projectDir."/data"
        ]);
    }
}
