<?php

namespace Base\Twig\Renderer\Adapter;

use Base\Cache\SimpleCacheInterface;
use Base\Service\LocaleProviderInterface;
use Base\Traits\SimpleCacheTrait;
use Base\Twig\Environment;
use Base\Twig\Renderer\AbstractTagRenderer;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollectionInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;
use Symfony\WebpackEncoreBundle\Exception\EntrypointNotFoundException;
use Symfony\WebpackEncoreBundle\Exception\UndefinedBuildException;

class EncoreTagRenderer extends AbstractTagRenderer implements SimpleCacheInterface
{
    private bool $saveDeferred = false;
    private ?CacheItemPoolInterface $cache = null;
    use SimpleCacheTrait;

    public function __construct(Environment $twig, LocaleProviderInterface $localeProvider, EntrypointLookupCollectionInterface $entrypointLookupCollection, string $publicDir, string $cacheDir)
    {
        parent::__construct($twig, $localeProvider);
        $this->entrypointLookupCollection = $entrypointLookupCollection;
        $this->publicDir = $publicDir;

        // NB:: $this->getCache() issue..
        // $this->cacheDir = $cacheDir;
        // $cacheFile = $cacheDir."/simple_cache/".str_replace(['\\', '/'], ['__', '_'], static::class).".php";
        // $this->setCache(new PhpArrayAdapter($cacheFile, new FilesystemAdapter()));
        // $this->warmUp($cacheDir);
    }

    public function warmUp(string $cacheDir): bool
    {
        $this->encoreEntrypoints = $this->getCache("/Entrypoints", $this->encoreEntrypoints) ?? [];
        return true;
    }

    protected ?array $encoreEntrypoints;
    protected  array $encoreEntryLinkTags   = [];
    protected  array $encoreEntryScriptTags = [];

    public function getEntryLinkTags() { return $this->encoreEntryLinkTags; }
    public function getEntryScriptTags() { return $this->encoreEntryScriptTags; }
    public function getEntrypoints(): array { return $this->encoreEntrypoints; }
    public function getEntry(string $entrypointName): ?EntrypointLookupInterface
    {
        return $this->encoreEntrypoints[$entrypointName] ?? null;
    }

    public function addEntrypoint(string $value, string $entrypointJsonPath, CacheItemPoolInterface $cache = null, string $cacheKey = null, bool $strictMode = true)
    {
        $this->encoreEntrypoints[$value] = new EntrypointLookup($entrypointJsonPath, $cache, $cacheKey, $strictMode);
        return $this;
    }

    public function hasEntry(string $entryName, string $entrypointName = '_default'): bool
    {
        if($this->entrypointLookupCollection === null) return false;

        $entrypointLookup = $this->entrypointLookupCollection?->getEntrypointLookup($entrypointName);
        if (!$entrypointLookup instanceof EntrypointLookup) {
            throw new \LogicException(sprintf('Cannot use entryExists() unless the entrypoint lookup is an instance of "%s"', EntrypointLookup::class));
        }

        return $entrypointLookup->entryExists($entryName);
    }

    public function addTag(string $value, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, ?string $htmlAttributes = null)
    {
        $this->addLinkTag($value, $webpackPackageName, $webpackEntrypointName, $htmlAttributes);
        $this->addScriptTag($value, $webpackPackageName, $webpackEntrypointName, $htmlAttributes);

        return $this;
    }

    public function addLinkTag(string $value, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, ?string $htmlAttributes = null)
    {
        if(!array_key_exists($value, $this->encoreEntryLinkTags)) {

            $this->encoreEntryLinkTags[$value] = array_filter([
                "value" => $value,
                "webpack_package_name" => $webpackPackageName,
                "webpack_entrypoint_name" => $webpackEntrypointName,
                "html_attributes" => $htmlAttributes
            ]);
        }

        return $this;
    }
    public function addScriptTag(string $value, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, ?string $htmlAttributes = null)
    {
        if(!array_key_exists($value, $this->encoreEntryScriptTags)) {

            $this->encoreEntryScriptTags[$value] = array_filter([
                "value" => $value,
                "webpack_package_name" => $webpackPackageName,
                "webpack_entrypoint_name" => $webpackEntrypointName,
                "html_attributes" => $htmlAttributes
            ]);
        }

        return $this;
    }

    public function removeTags(string $value)
    {
        $this->removeLinkTag($value);
        $this->removeScriptTag($value);

        return $this;
    }

    public function removeLinkTag(string $value)
    {
        if(array_key_exists($value, $this->encoreEntryLinkTags))
            unset($this->encoreEntryLinkTags[$value]);

        return $this;
    }

    public function removeScriptTag(string $value)
    {
        if($this->entrypointLookupCollection == null) return $this;

        if(array_key_exists($value, $this->encoreEntryScriptTags))
            unset($this->encoreEntryScriptTags[$value]);

        return $this;
    }

    public function renderCssSource(string|array $value, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, ?string $htmlAttributes = null) : string
    {
        $entryName = is_array($value) ? $value["value"] ?? null : $value;
        if($entryName == null) return "";

        if($entryName && !array_key_exists($entryName, $this->encoreEntryLinkTags))
            $this->addLinkTag($value, $webpackPackageName, $webpackEntrypointName, $htmlAttributes);

        $source = "";
        $entrypoints = $this->getEntrypoints();
        $entrypoints["_default"] = $this->entrypointLookupCollection->getEntrypointLookup();
        foreach($entrypoints as $entrypoint) {

            try { $files = $entrypoint->getCssFiles($entryName); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

            foreach ($files as $file)
                $source .= file_get_contents($this->publicDir.'/'.$file);

            $this->removeLinkTag($entryName);
            $entrypoint->reset();

            return $source;
        }

        throw new EntrypointNotFoundException("Failed to find \"$entryName\" in the lookup collection: ".implode(", ", array_keys($entrypoints)));
    }

    public function renderLinkTags(null|string|array $value = null, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, ?string $htmlAttributes = null) : string
    {
	    if($this->entrypointLookupCollection == null) return "";

        $entryName = is_array($value) ? $value["value"] ?? null : $value;
        if($entryName == null) return "";
        if($entryName && !array_key_exists($entryName, $this->encoreEntryLinkTags))
            $this->addLinkTag($value, $webpackPackageName, $webpackEntrypointName, $htmlAttributes);

        $tags = [];
        $entrypoints = $this->getEntrypoints();
        $entrypoints["_default"] = $this->entrypointLookupCollection->getEntrypointLookup();

        foreach($entrypoints as $entrypoint) {

            $render = "";
            for($i = 0; $i < 2; $i++) {

                $deferEntry = $i ? ".defer" : "";
                try { $files = $entrypoint->getCssFiles($entryName.$deferEntry); }
                catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

                if($files) {

                    $tags = array_map(fn($e) => ["value" => $e, "defer" => !empty($deferEntry)], $files);
                    $this->removeLinkTag($entryName);

                    $render .= $this->twig->render("@Base/webpack/link_tags.html.twig", ["tags" => $tags]);
                }
            }

            if($render) return $render;
        }

        throw new EntrypointNotFoundException("Failed to find \"$entryName\" in the lookup collection: ".implode(", ", array_keys($entrypoints)));
    }

    public function renderScriptTags(null|string|array $value = null, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, ?string $htmlAttributes = null) : string
    {
	    if($this->entrypointLookupCollection == null) return "";

        $entryName = is_array($value) ? $value["value"] ?? null : $value;
        if($entryName == null) return "";

        if($entryName && !array_key_exists($entryName, $this->encoreEntryScriptTags))
            $this->addScriptTag($value, $webpackPackageName, $webpackEntrypointName, $htmlAttributes);

        $entrypoints = $this->getEntrypoints();
        $entrypoints["_default"] = $this->entrypointLookupCollection->getEntrypointLookup();

        foreach($entrypoints as $entrypoint) {

            $render = "";
            for($i = 0; $i < 2; $i++) {

                $deferEntry = $i ? ".defer" : "";
                try { $files = $entrypoint->getJavaScriptFiles($entryName.$deferEntry); }
                catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

                if($files) {

                    $tags = array_map(fn($e) => ["value" => $e, "defer" => !empty($deferEntry)], $files);
                    $this->removeScriptTag($entryName);

                    $render .= $this->twig->render("@Base/webpack/script_tags.html.twig", ["tags" => $tags]);
                }
            }

            if($render) return $render;
        }

        dump($entryName, $value, $render);
        dump($entrypoints);
        return "";

        throw new EntrypointNotFoundException("Failed to find \"$entryName\" in the lookup collection: ".implode(", ", array_keys($entrypoints)));
    }

    public function render(string $value, ?array $context = [], ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, ?string $htmlAttributes = null) : string
    {
        return $this->renderLinkTags  ($value, $webpackPackageName, $webpackEntrypointName, $htmlAttributes).PHP_EOL.
               $this->renderScriptTags($value, $webpackPackageName, $webpackEntrypointName, $htmlAttributes);
    }

    public function renderFallback(Response $response): Response
    {
        $content = $response->getContent();

        foreach($this->getEntryLinkTags() as $tag)
            $content = preg_replace('/<\/head\b[^>]*>/', $this->renderLinkTags($tag)."$0", $content, 1);

        foreach($this->getEntryScriptTags() as $tag)
            $content = preg_replace('/<\/body\b[^>]*>/', $this->renderScriptTags($tag)."$0", $content, 1);

        $response->setContent($content);
        return $response;
    }
}