<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

interface SitemapperInterface
{
    public function getHostname(): string;
    public function setHostname(string $hostname): self;

    public function register(string|Route $routeOrName, array $routeParameters = []): self;
    public function registerUrl(string $url): self;
    public function registerAnnotations(): self;

    public function serve(string $name, array $context = []): Response;
}
