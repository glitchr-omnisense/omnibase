<?php

namespace Base\Twig\Renderer;

use Symfony\Component\HttpFoundation\Response;

interface TagRendererInterface
{
    public function render(string $name, array $context = []): string;
    public function renderFallback(Response $response): Response;
}