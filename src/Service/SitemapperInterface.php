<?php

namespace Base\Service;

use Base\Response\XmlResponse;

interface SitemapperInterface
{
    public function getHostname(): string;
    public function setHostname(string $hostname): self;

    public function register(string $routeName, array $routeParameters): self;
    public function registerUrl(string $routeName, string $url): self;
    public function registerAnnotations(): self;

    public function generate(string $name, array $context = []): XmlResponse;
}