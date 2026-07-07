<?php

namespace Base\Service\Model\Sharing\Adapter;

use Base\Service\Model\Sharing\AbstractSharingAdapter;

class TwitterAdapter extends AbstractSharingAdapter
{
    public function getIdentifier(): string
    {
        return "twitter";
    }

    public function getUrl(): string
    {
        return "https://twitter.com/intent/tweet?text={text}&url={url}";
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-brands fa-twitter", "fa-brands fa-twitter-p", "fa-brands fa-twitter-square"];
    }
}
