<?php

namespace Base\Model\IconProvider;

interface IconAdapterInterface
{
    public function load(): array;
    public function supports(string $icon): bool;
    public function iconify(string $icon, array $attributes): string;

    public static function getName(): string;
    public static function getOptions(): array;

    public function getVersion(): string;
    public function getAssets(): array;
    public function getChoices(string $term = ""); // To be used in IconType 
}
