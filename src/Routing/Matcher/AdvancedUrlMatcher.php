<?php

namespace Base\Routing\Matcher;

use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;

class AdvancedUrlMatcher extends CompiledUrlMatcher
{
    public function match(string $pathinfo): array
    {
        $parse = parse_url2();
        $parse = array_merge($parse, parse_url2($pathinfo));

        $this->getContext()->setHost($parse["host"]);

        return parent::match($pathinfo);
    }
}
