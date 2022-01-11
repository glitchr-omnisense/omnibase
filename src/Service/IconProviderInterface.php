<?php

namespace Base\Service;

interface IconProviderInterface
{
    public function load(): array;
    public function supports(string $icon): bool;
    public function iconify(string $icon, array $attributes): string;

    public function getChoices(string $term); // To be used in IconType 
}
