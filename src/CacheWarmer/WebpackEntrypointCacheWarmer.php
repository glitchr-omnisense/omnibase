<?php

namespace Base\CacheWarmer;

use Base\Cache\Abstract\AbstractLocalCacheWarmer;
use Base\Service\ParameterBagInterface;
use Base\Twig\Renderer\Adapter\EncoreTagRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;

class WebpackEntrypointCacheWarmer extends AbstractLocalCacheWarmer
{
    public function __construct(ParameterBagInterface $parameterBag, EncoreTagRenderer $encoreTagRenderer, ?EntrypointLookupInterface $entrypointLookup, string $cacheDir, string $publicDir)
    {
        if(!$parameterBag->get("base.twig.use_custom")) return;
        if(!$entrypointLookup) return;
        
        // Extract [app] tags
        $appJsonPath = array_filter((array) $entrypointLookup, fn($k) => str_ends_with($k, "entrypointJsonPath"), ARRAY_FILTER_USE_KEY);
        $appJsonPath = first($appJsonPath);
        if(file_exists($appJsonPath)) {

            $encoreTagRenderer->addEntrypoint("_default", $appJsonPath);
            $entrypoints = json_decode(file_get_contents($appJsonPath), true)["entrypoints"];

            $tags = array_unique(array_map(fn($t) => str_rstrip($t, ["-async", "-defer"]), array_keys($entrypoints)));
            foreach($tags as $tag)
                $encoreTagRenderer->addTag($tag);
        }

        // Extract [base] tags 
        $baseJsonPath = str_rstrip($publicDir,"/")."/bundles/base/entrypoints.json";
        if(file_exists($baseJsonPath)) {

            $encoreTagRenderer->addEntrypoint("_base", $baseJsonPath);
            $entrypoints = json_decode(file_get_contents($baseJsonPath), true)["entrypoints"];
            
            $tags = array_unique(array_map(fn($t) => str_rstrip($t, ["-async", "-defer"]), array_keys($entrypoints)));
            foreach($tags as $tag)
                $encoreTagRenderer->addTag($tag, "_base");
        }

        //
        // Breakpoint based entries
        foreach($parameterBag->get("base.twig.breakpoints") ?? [] as $breakpoint)
            $encoreTagRenderer->addBreakpoint($breakpoint["name"], $breakpoint["media"] ?? "all");

        //
        // Alternative entries
        $encoreTagRenderer->addAlternative("async");
        $encoreTagRenderer->addAlternative("defer");

        // Encore rest rendering
        $encoreTagRenderer->renderFallback(new Response());
        $encoreTagRenderer->reset();

        parent::__construct($encoreTagRenderer, $cacheDir);
    }
}
