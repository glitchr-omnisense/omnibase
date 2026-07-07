<?php

namespace Base\Service\Model\Sharing\Adapter;

use Base\Service\Model\Sharing\AbstractSharingAdapter;

class PinterestAdapter extends AbstractSharingAdapter
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
