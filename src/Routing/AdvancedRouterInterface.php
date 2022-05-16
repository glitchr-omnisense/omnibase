<?php

namespace Base\Routing;

use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RouterInterface;

interface AdvancedRouterInterface extends RouterInterface, RequestMatcherInterface, WarmableInterface
{
    // TODO: Implement methods based on AdvancedRouter class
}