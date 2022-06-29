<?php

namespace Base\Model\Sharer\Adapter;

use Base\Model\Sharer\AbstractSharerAdapter;

class TumblrAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string { return "tumblr"; }
    public function getUrl(): string { return "https://www.tumblr.com/share/link?title={title}&description={text}&url={url}"; }
    public static function __iconizeStatic(): ?array
    {
        return ["fab fa-tumblr", "fab fa-tumblr-square"];
    }
}