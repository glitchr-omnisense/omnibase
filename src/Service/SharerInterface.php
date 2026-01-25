<?php

namespace Base\Service;

use Base\Service\Model\LinkableInterface;

interface SharerInterface
{
    public function share(string $adapterId, LinkableInterface|string $url, array $options = [], ?string $template = null);
}
