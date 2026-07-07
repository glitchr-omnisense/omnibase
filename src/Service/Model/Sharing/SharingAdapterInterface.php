<?php

namespace Base\Service\Model\Sharing;

interface SharingAdapterInterface
{
    public function getIdentifier(): string;

    public function getUrl(): string;

    public function getTemplate(): string;

    public function generate(array $options, ?string $template = null): string;
}
