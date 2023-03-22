<?php

namespace Base\Service;

use Base\Service\Model\LinkableInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

interface SharerInterface
{
    public function share(string $adapterId, LinkableInterface|string $url, array $options = [], ?string $template = null);
}
