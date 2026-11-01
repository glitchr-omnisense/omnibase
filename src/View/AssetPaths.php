<?php

namespace Base\View;

/**
 * An asset's path as the page links it (plan "omnibase agnostic", step F4): an address as it is, a
 * path under the request's base folder. Under Twig, omnibase's environment answers it
 * (Base\Twig\Environment, through its HTML renderer); a form type that shows a thumbnail asks it
 * without naming the view engine.
 */
interface AssetPaths
{
    public function getAsset(string $url): string;
}
