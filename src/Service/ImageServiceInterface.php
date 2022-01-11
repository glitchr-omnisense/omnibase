<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Response;

interface ImageServiceInterface
{
    public function resolve(array|string|null $path, array $filters = [], array $options = []): array|string|null;
    public function filter(string $path, array $filters = []): Response;
}