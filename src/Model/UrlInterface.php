<?php

namespace Base\Model;

interface UrlInterface
{
    public function __toString();
    public function __toUrl(): ?string;
}
