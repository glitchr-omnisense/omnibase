<?php

namespace Base\Model\Sharer\Adapter;

use Base\Model\Sharer\AbstractSharerAdapter;

class TwitterAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string { return "twitter"; }
    public function getUrl(): string { return "https://twitter.com/intent/tweet?text={text}&url={url}"; }
    public static function __iconizeStatic(): ?array
    {
        return ["fab fa-twitter", "fab fa-twitter-p", "fab fa-twitter-square"];
    }
}
