<?php

namespace Base\Twig\Renderer\Adapter;

use Base\Twig\Environment;
use Base\Twig\AssetPackage;
use InvalidArgumentException;
use Base\Traits\SimpleCacheTrait;
use Base\Cache\SimpleCacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Asset\Packages;
use Base\Service\ParameterBagInterface;
use Base\Service\LocaleProviderInterface;
use Base\Twig\Renderer\AbstractTagRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;
use Symfony\WebpackEncoreBundle\Exception\UndefinedBuildException;
use Symfony\WebpackEncoreBundle\Exception\EntrypointNotFoundException;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollectionInterface;

class EncoreTagRenderer extends AbstractTagRenderer implements SimpleCacheInterface
{
    /**
     * @var Packages
     */
    protected $packages;

    /**
     * @var ?EntrypointLookupCollectionInterface
     */
    protected $entrypointLookupCollection;

    /** @var string */
    protected string $publicDir;
    /** @var string */
    protected string $cacheDir;

    private bool $saveDeferred = false;
    private ?CacheItemPoolInterface $cache = null;
    use SimpleCacheTrait;

    public function __construct(
        Environment $twig, LocaleProviderInterface $localeProvider, SluggerInterface $slugger, ParameterBagInterface $parameterBag,
        ?EntrypointLookupCollectionInterface $entrypointLookupCollection, Packages $packages, string $publicDir, string $cacheDir)
    {
        $this->entrypointLookupCollection = $entrypointLookupCollection;
        if( $this->entrypointLookupCollection == null) return;

        parent::__construct($twig, $localeProvider, $slugger, $parameterBag);
        $this->publicDir = $publicDir;
        $this->packages = $packages;

        // NB:: $this->getCache() issue..
        $this->cacheDir = $cacheDir;
        $cacheFile = $cacheDir."/simple_cache/".str_replace(['\\', '/'], ['__', '_'], static::class).".php";
        $this->setCache(new PhpArrayAdapter($cacheFile, new FilesystemAdapter()));

        $this->warmUp($cacheDir);
    }

    public function warmUp(string $cacheDir): bool
    {
        $this->encoreEntrypoints  = $this->getCache("/Entrypoints", $this->encoreEntrypoints ?? []);
        $this->renderedCssSource  = $this->getCache("/Source", $this->renderedCssSource ?? []);
        $this->renderedLinkTags   = $this->getCache("/Link" , $this->renderedLinkTags ?? []);
        $this->renderedScriptTags = $this->getCache("/Script", $this->renderedScriptTags ?? []);

        return true;
    }

    protected array $encoreEntrypoints     = [];
    protected array $encoreEntryLinkTags   = [];
    protected array $encoreEntryScriptTags = [];

    protected array $encoreBreakpoints = [];

    public function getMedia(string $filename): ?string
    {
        foreach($this->encoreBreakpoints as $media => $names) {

            foreach($names as $name)
                if(str_contains($filename, "-".$name)) return $media;
        }

        return null;
    }

    public function getBreakpoints() { return $this->encoreBreakpoints; }
    public function addBreakpoint(string $name, string $media)
    {
        $this->encoreBreakpoints[$media] = array_merge($this->encoreBreakpoints[$media] ?? [], [$name]);
        return $this;
    }
    public function removeBreakpoint(string $name)
    {
        array_remove($this->encoreBreakpoints, $name);
        return $this;
    }

    protected array $encoreAlternatives = [];
    public function getAlternatives() { return $this->encoreAlternatives; }
    public function addAlternative(string $alternative)
    {
        if(!array_key_exists($alternative, $this->encoreAlternatives))
            $this->encoreAlternatives[] = $alternative;

        return $this;
    }
    public function removeAlternative(string $alternative)
    {
        array_remove($this->encoreAlternatives, $alternative);
        return $this;
    }

    public function getEntryLinkTags(): array { return $this->encoreEntryLinkTags; }
    public function getEntryScriptTags(): array { return $this->encoreEntryScriptTags; }
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

        try { return $entrypointLookup->entryExists($entryName); }
        catch (InvalidArgumentException $e) { return false; }
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

    protected array $renderedCssSource = [];
    protected array $renderedScriptTags = [];
    protected array $renderedLinkTags = [];

    public function reset($entrypoints = null)
    {
        if($entrypoints && !is_array($entrypoints)) $entrypoints = [$entrypoints];
        foreach($entrypoints ?? $this->getEntrypoints() as $entrypoint)
            $entrypoint->reset();
    }

    public function renderCssSource(string|array $value, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, ?string $htmlAttributes = null) : string
    {
        $value = is_array($value) ? $value["value"] ?? null : $value;

        $entryName = $value;
        if(!$entryName) return "";

        $entryName = (string) $this->slugger->slug($entryName);
        if(array_key_exists($entryName, $this->renderedCssSource))
            return $this->renderedCssSource[$entryName];

        if(!array_key_exists($entryName, $this->renderedCssSource))
            $this->addLinkTag($value, $webpackPackageName, $webpackEntrypointName, $htmlAttributes);

        $source = "";
        foreach($this->getEntrypoints() as $entrypoint) {

            try { $files = $entrypoint->getCssFiles($entryName); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { continue; }

            foreach ($files as $file)
                $source .= file_get_contents($this->publicDir.'/'.$file);

            $this->removeLinkTag($entryName);
            $entrypoint->reset();

            $this->renderedCssSource[$entryName] = $source;
            return $this->renderedCssSource[$entryName];
        }

        throw new EntrypointNotFoundException("Failed to find \"".$entryName."\" in the lookup collection: ".implode(", ", array_keys($this->getEntrypoints())));
    }

    public function renderLinkTags(null|string|array $value = null, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, array $htmlAttributes = []) : string
    {
	    if($this->entrypointLookupCollection == null) return "";

        $value = is_array($value) ? $value["value"] ?? null : $value;

        $entryName = $value;
        if(!$entryName) return "";

        $entryName = (string) $this->slugger->slug($entryName);
        if(array_key_exists($entryName, $this->renderedLinkTags))
            return $this->renderedLinkTags[$entryName];

        if(!array_key_exists($entryName, $this->encoreEntryLinkTags))
            $this->addLinkTag($value, $webpackPackageName, $webpackEntrypointName, html_attributes($htmlAttributes));

        $tags = [];
        foreach($this->getEntrypoints() as $entrypoint) {

            $files = [];

            try { $files = array_merge($files, $entrypoint->getCssFiles($this->slugger->slug($entryName))); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { }
            foreach($this->encoreAlternatives as $alternative) {

                try { $files = array_merge($files, $entrypoint->getCssFiles($this->slugger->slug($entryName."-".$alternative))); }
                catch(UndefinedBuildException|EntrypointNotFoundException $e) { }
            }

            /**
             * @var AssetPackage
             */
            $basePackage = $this->packages->getPackage(AssetPackage::PACKAGE_NAME);
            $appPackage  = $this->packages->getPackage();
            for($i = 0, $N = count($files); $i < $N; $i++) {

                foreach($this->encoreBreakpoints as $_) foreach($_ as $name) {

                    $file = explode(".", $files[$i])[0];
                    $file = path_suffix($file, $name, "-").".css";
                    if(str_starts_with($file, $basePackage->getBasePath())) {
                        $file = $basePackage->getUrl("./".$basePackage->stripPrefix($file));
                    } else {
                        $file = $appPackage->getUrl("./".str_lstrip($file, "/assets/")); // @TODO Use webpack encore output path injection
                    }

                    if(file_exists($this->publicDir.$file))
                        $files[] = $file;
                }
            }

            if(!$files) continue;
            $tags = array_filter(array_map(fn($e) => [
                "value" => $e,
                "defer" => str_contains($e, "-defer") || $this->defaultLinkAttributes["defer"],
                "async" => str_contains($e, "-async") || $this->defaultLinkAttributes["async"],
                "media" => $this->getMedia($e)
            ], $files));

            $this->removeLinkTag($entryName);

            $this->renderedLinkTags[$entryName] = $this->twig->render("@Base/webpack/link_tags.html.twig", ["tags" => $tags]);
            return $this->renderedLinkTags[$entryName];
        }

        throw new EntrypointNotFoundException("Failed to find \"".$entryName."\" in the lookup collection: ".implode(", ", array_keys($this->getEntrypoints())));
    }

    public function renderScriptTags(null|string|array $value = null, ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, array $htmlAttributes = []) : string
    {
	    if($this->entrypointLookupCollection == null) return "";

        $value = is_array($value) ? $value["value"] ?? null : $value;

        $entryName = $value;
        if(!$entryName) return "";

        $entryName = (string) $this->slugger->slug($entryName);
        if(array_key_exists($entryName, $this->renderedScriptTags))
            return $this->renderedScriptTags[$entryName];

        if(!array_key_exists($entryName, $this->encoreEntryScriptTags))
            $this->addScriptTag($value, $webpackPackageName, $webpackEntrypointName, html_attributes($htmlAttributes));

        foreach($this->getEntrypoints() as $entrypoint) {

            $files = [];

            try { $files = array_merge($files, $entrypoint->getJavaScriptFiles($this->slugger->slug($entryName))); }
            catch(UndefinedBuildException|EntrypointNotFoundException $e) { }
            foreach($this->encoreAlternatives as $alternative) {

                try { $files = array_merge($files, $entrypoint->getJavaScriptFiles($this->slugger->slug($entryName."-".$alternative))); }
                catch(UndefinedBuildException|EntrypointNotFoundException $e) { }
            }

            if(!$files) continue;

            $tags = array_filter(array_map(fn($e) => [
                "value" => $e,
                "defer" => str_contains($e, "-defer") || (!str_contains($e, "-async") && $this->defaultScriptAttributes["defer"]),
                "async" => str_contains($e, "-async") || (!str_contains($e, "-defer") && $this->defaultScriptAttributes["async"])
            ], $files));

            $this->removeScriptTag($entryName);

            $this->renderedScriptTags[$entryName] = $this->twig->render("@Base/webpack/script_tags.html.twig", ["tags" => $tags]);
            return $this->renderedScriptTags[$entryName];
        }

        // Script not mandatory
        // throw new EntrypointNotFoundException("Failed to find \"".$entryName."\" in the lookup collection: ".implode(", ", array_keys($this->getEntrypoints())));
        return "";
    }

    public function render(string $value, ?array $context = [], ?string $webpackPackageName = null, ?string $webpackEntrypointName = null, array $htmlAttributes = []) : string
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
