<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

interface SitemapperInterface
{
    public function getHostname(): string;

    public function setHostname(string $hostname): self;

    public function register(string|Route $routeOrName, array $routeParameters = [], ?string $lastMod = null): self;

    public function registerUrl(string $url, ?string $lastMod = null): self;

    public function registerAttributes(): self;

    public function serve(string $name, array $context = []): Response;
}
