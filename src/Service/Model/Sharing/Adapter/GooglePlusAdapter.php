<?php

namespace Base\Service\Model\Sharing\Adapter;

use Base\Service\Model\Sharing\AbstractSharingAdapter;

class GooglePlusAdapter extends AbstractSharingAdapter
{
    public function getIdentifier(): string
    {
        return "google+";
    }

    public function getUrl(): string
    {
        return "https://plus.google.com/share?url={url}";
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-brands fa-google-plus", "fa-brands fa-google-plus-square", "fa-brands fa-google-plus-g"];
    }
}
