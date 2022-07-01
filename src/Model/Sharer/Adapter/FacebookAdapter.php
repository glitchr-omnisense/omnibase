<?php

namespace Base\Model\Sharer\Adapter;

use Base\Model\Sharer\AbstractSharerAdapter;

class FacebookAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string { return "facebook"; }
    public function getUrl(): string { return "https://www.facebook.com/sharer/sharer.php?quote={text}&u={url}"; }
    public static function __iconizeStatic(): ?array
    {
        return ["fab fa-facebook", "fab fa-facebook-square", "fab fa-facebook-f", "fab fa-facebook-messenger"];
    }
}
