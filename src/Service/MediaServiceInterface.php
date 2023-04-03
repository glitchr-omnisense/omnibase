<?php

namespace Base\Service;

use Base\Imagine\FilterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface MediaServiceInterface extends FileServiceInterface
{
    public function isCached(?string $path, FilterInterface|array $filters = [], array $config = []): bool;
    public function resolve(string $hashid): array;

    public function image(array|string|null $path, array $filters = [], array $config = []): array|string|null;
    public function filter(?string $path, array $filters = [], array $config = []): ?string;
    public function thumbnail(array|string|null $path, ?int $width = null, ?int $height = null, array $filters = [], array $config = []): array|string|null;

    public function audio(array|string|null $path, array $filters = [], array $config = []): array|string|null;
    public function video(array|string|null $path, array $filters = [], array $config = []): array|string|null;
}
