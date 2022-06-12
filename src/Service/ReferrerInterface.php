<?php

namespace Base\Service;

interface ReferrerInterface
{
    public function getUrl() : ?string;
    public function setUrl(?string $url);
}