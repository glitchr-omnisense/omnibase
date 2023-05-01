<?php

namespace Base\Service\Model\Sharer\Adapter;

use Base\Service\Model\Sharer\AbstractSharerAdapter;

/**
 *
 */
class PinterestAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string
    {
        return "pinterest";
    }

    public function getUrl(): string
    {
        return "https://pinterest.com/pin/create/button/?description={description}&url={url}&media={media}";
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-brands fa-pinterest", "fa-brands fa-pinterest-square", "fa-brands fa-pinterest-p"];
    }
}
