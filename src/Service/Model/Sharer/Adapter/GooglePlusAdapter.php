<?php

namespace Base\Service\Model\Sharer\Adapter;

use Base\Service\Model\Sharer\AbstractSharerAdapter;

class GooglePlusAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string { return "google+"; }
    public function getUrl(): string { return "https://plus.google.com/share?url={url}"; }
    public static function __iconizeStatic(): ?array
    {
        return ["fab fa-google-plus", "fab fa-google-plus-square", "fab fa-google-plus-g"];
    }
}
