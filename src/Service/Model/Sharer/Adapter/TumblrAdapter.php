<?php

namespace Base\Service\Model\Sharer\Adapter;

use Base\Service\Model\Sharer\AbstractSharerAdapter;

class TumblrAdapter extends AbstractSharerAdapter
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
