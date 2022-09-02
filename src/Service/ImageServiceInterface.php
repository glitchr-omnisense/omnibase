<?php

namespace Base\Service;

use Base\Imagine\FilterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface ImageServiceInterface extends FileServiceInterface
{
    public function filter(?string $path, array $filters = [], array $config = []): ?string;
    public function isCached(?string $path, FilterInterface|array $filters = [], array $config = []): bool;
    public function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse;

    public function webp   (array|string|null $path, array $filters = [], array $config = []): array|string|null;
    public function image  (array|string|null $path, array $filters = [], array $config = []): array|string|null;
    public function imagine(array|string|null $path, array $filters = [], array $config = []): array|string|null;
    public function thumbnail(array|string|null $path, ?int $width = null , ?int $height = null, array $filters = [], array $config = []): array|string|null;
}