<?php

namespace Base\Service\Model\Sharer\Adapter;

use Base\Service\Model\Sharer\AbstractSharerAdapter;

class PinterestAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string { return "pinterest"; }
    public function getUrl(): string { return "https://pinterest.com/pin/create/button/?description={text}&url={url}&media={media}"; }
    public static function __iconizeStatic(): ?array
    {
        return ["fab fa-pinterest", "fab fa-pinterest-square", "fab fa-pinterest-p"];
    }
}
