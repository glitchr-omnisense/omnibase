<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Response;

interface ImageServiceInterface
{
    public function filter(?string $path, array $filters = [], array $config = []): ?string;
}