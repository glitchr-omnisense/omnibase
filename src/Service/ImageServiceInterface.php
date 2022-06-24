<?php

namespace Base\Service;

use Base\Imagine\FilterInterface;

interface ImageServiceInterface extends FileServiceInterface
{
    public function filter(?string $path, array $filters = [], array $config = []): ?string;
    public function isCached(?string $path, FilterInterface|array $filters = [], array $config = []): bool;
    public function resolve(string $hashid, array $filters = []);

    public function webp   (array|string|null $path, array $filters = [], array $config = []): array|string|null;
    public function image  (array|string|null $path, array $filters = [], array $config = []): array|string|null;
    public function imagine(array|string|null $path, array $filters = [], array $config = []): array|string|null;
    public function thumbnail(array|string|null $path, ?int $width = null , ?int $height = null, array $filters = [], array $config = []): array|string|null;
}