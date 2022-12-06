<?php

namespace Base\Service\Model\IconProvider;

use Base\Service\Model\IconizeInterface;

interface IconAdapterInterface
{
    public function supports(IconizeInterface|string|null $icon): bool;
    public function iconify (IconizeInterface|string $icon, array $attributes): string;

    public static function getName(): string;
    public static function getOptions(): array;

    public function getVersion(): string;
    public function getAssets(): array;
    public function getChoices(string $term = ""); // To be used in IconType
}
