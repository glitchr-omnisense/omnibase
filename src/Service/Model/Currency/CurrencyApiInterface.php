<?php

namespace Base\Service\Model\Currency;

/**
 *
 */
interface CurrencyApiInterface
{
    public function supports(string $key): bool;

    public function getPriority(): int;

    public function getOptions(): array;

    public function getKey(): ?string;
}
