<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\RedirectResponse;

interface ImageServiceInterface
{
    public function resolve(array|string|null $path, array $config = []): array|string|null;
    public function filter(string $path, array $filters = []): RedirectResponse;
}