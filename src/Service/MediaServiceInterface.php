<?php

namespace Base\Service;

use Base\Imagine\FilterInterface;

use Base\Controller\UX\MediaController;

/**
 *
 */
interface MediaServiceInterface extends FileServiceInterface
{
    public function isCached(?string $path, array $config = [], FilterInterface|array $filters = []): bool;

    public function resolve(string $data): array;

    public function image(array|string|null $path, array $config = [], FilterInterface|array $filters = []): array|string|null;

    public function filter(?string $path, array $config = [], FilterInterface|array $filters = []): ?string;

    public function thumbnail(array|string|null $path, ?int $width = null, ?int $height = null, array $config = [], FilterInterface|array $filters = []): array|string|null;

    public function audio(array|string|null $path, array $config = [], FilterInterface|array $filters = []): array|string|null;

    public function video(array|string|null $path, array $config = [], FilterInterface|array $filters = []): array|string|null;

    public function setController(MediaController $mediaController);
}
