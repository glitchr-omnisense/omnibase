<?php

namespace Base\Service;

use Base\Service\Model\LinkableInterface;

interface SharingInterface
{
    public function generate(string $adapterId, LinkableInterface|string $url, array $options = [], ?string $template = null);
}
