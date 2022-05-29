<?php

namespace Base\Routing\Matcher;

use Exception;
use Symfony\Bundle\FrameworkBundle\Routing\RedirectableCompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;

class AdvancedUrlMatcher extends RedirectableCompiledUrlMatcher implements RedirectableUrlMatcherInterface
{
    public function match(string $pathinfo): array
    {
        $parse = parse_url2();
        $parse = array_merge($parse, parse_url2($pathinfo));

        $this->getContext()->setHost($parse["host"]);

        return parent::match($parse["path"] ?? $pathinfo);
    }
}
