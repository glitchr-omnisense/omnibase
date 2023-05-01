<?php

namespace Base\Service;

/**
 *
 */
interface ReferrerInterface
{
    public function getUrl(): ?string;

    public function setUrl(?string $url);

    public function clear();

    public function sameSite(): bool;
}
