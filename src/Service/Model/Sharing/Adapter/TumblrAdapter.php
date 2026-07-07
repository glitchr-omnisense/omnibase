<?php

namespace Base\Service\Model\Sharing\Adapter;

use Base\Service\Model\Sharing\AbstractSharingAdapter;

class TumblrAdapter extends AbstractSharingAdapter
{
    public function getIdentifier(): string
    {
        return "tumblr";
    }

    public function getUrl(): string
    {
        return "https://www.tumblr.com/share/link?title={title}&description={description}&url={url}";
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-brands fa-tumblr", "fa-brands fa-tumblr-square"];
    }
}
