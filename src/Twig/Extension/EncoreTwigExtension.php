<?php

namespace Base\Twig\Extension;

use Base\Twig\Environment;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollectionInterface;
use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use Symfony\WebpackEncoreBundle\Exception\EntrypointNotFoundException;
use Symfony\WebpackEncoreBundle\Exception\UndefinedBuildException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EncoreTwigExtension extends AbstractExtension
{
    public function __construct(?TagRenderer $tagRenderer = null, ?EntrypointLookupCollectionInterface $entrypointLookupCollection = null, ?string $publicDir = null)
    {
        $this->publicDir        = $publicDir;   
        $this->tagRenderer      = $tagRenderer;
        $this->entrypointLookupCollection = $entrypointLookupCollection;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('encore_entry_link_tags',    [$this, 'renderWebpackLinkTags'   ], ["is_safe" => ['all'], 'needs_environment' => true]),
            new TwigFunction('encore_entry_script_tags',  [$this, 'renderWebpackScriptTags' ], ["is_safe" => ['all'], 'needs_environment' => true]),
            new TwigFunction('encore_entry_css_source',   [$this, 'renderWebpackCssSource'  ], ["is_safe" => ['all'], 'needs_environment' => true]),
        ];
    }

    public function renderWebpackCssSource(Environment $env, string $entry): string
    {
        $entryName = is_array($entry) ? $entry["value"] ?? null : $entry;
        if($entryName == null) return "";

        $source = "";
        $entryPoints = $env->getEncoreEntrypoints();
        $entryPoints["_default"] = $this->entrypointLookupCollection->getEntrypointLookup();
        foreach($entryPoints as $entryPoint) {
            
            try { $files = $entryPoint->getCssFiles($entryName); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

            foreach ($files as $file) {
                $source .= file_get_contents($this->publicDir.'/'.$file);
            }

            break;
        }

        return $source;
        throw new EntrypointNotFoundException("Failed to find \"$entryName\" in the lookup collection: ".implode(", ", array_keys($entryPoints)));
    }

    public function renderWebpackScriptTags(Environment $env, string|array $entry) : string
    {
	if($this->entrypointLookupCollection == null) return "";

        $entryName = is_array($entry) ? $entry["value"] ?? null : $entry;
        if($entryName == null) return "";

        $entryPoints   = $env->getEncoreEntrypoints();
        $entryPoints["_default"] = $this->entrypointLookupCollection->getEntrypointLookup();
        foreach($entryPoints as $entryPoint) {

            try { $files = $entryPoint->getJavaScriptFiles($entryName); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

            if($files) {

                $tags = array_map(fn($e) => ["value" => $e], $files);
                return $env->render("@Base/webpack/script_tags.html.twig", ["tags" => $tags]);
            }
        }

        throw new EntrypointNotFoundException("Failed to find \"$entryName\" in the lookup collection: ".implode(", ", array_keys($entryPoints)));
    }

    public function renderDefaultWebpackLinkTags(string $entryName, string $packageName = null, string $entrypointName = '_default', array $attributes = []): ?string { return $this->tagRenderer?->renderWebpackLinkTags($entryName, $packageName, $entrypointName, $attributes); }
    public function renderWebpackLinkTags(Environment $env, string|array $entry) : string
    {
	if($this->entrypointLookupCollection == null) return "";

        $entryName = is_array($entry) ? $entry["value"] ?? null : $entry;
        if($entryName == null) return "";

        $tags = [];
        $entryPoints   = $env->getEncoreEntrypoints();
        $entryPoints["_default"] = $this->entrypointLookupCollection->getEntrypointLookup();
        foreach($entryPoints as $entryPoint) {

            try { $files = $entryPoint->getCssFiles($entryName); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

            if($files) {
                $tags = array_map(fn($e) => ["value" => $e], $files);
                return $env->render("@Base/webpack/link_tags.html.twig", ["tags" => $tags]);
            }
        }

        throw new EntrypointNotFoundException("Failed to find \"$entryName\" in the lookup collection: ".implode(", ", array_keys($entryPoints)));
    }
}
