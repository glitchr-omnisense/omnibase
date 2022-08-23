<?php

namespace Base\Service\Model;

interface CurrencyApiInterface
{
    public static function getName(): string;
    public function supports(): bool;

    public function getPriority(): int;
    public function getOptions(): array;
    public function getKey(): ?string;
}
