<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Response;

interface FileServiceInterface
{
    public function getExtension(null|string|array $fileOrMimetypeOrArray): ?string;

    public function getExtensions(null|string|array $fileOrMimetypeOrArray): null|string|array;

    public function getMimeType(null|string|array $fileOrContentsOrArray): null|string|array;

    public function generate(string $proxyRoute, array $proxyRouteParameters, ?string $path, array $config = []): ?string;

    public function resolve(string $data): ?array;

    public function serve(?string $path, int $status = 200, array $headers = []): null|bool|Response;

    public function serveContents(?string $contents, int $status = 200, array $headers = []): null|bool|Response;
}
