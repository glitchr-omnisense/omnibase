<?php

namespace Base\Service\Model\Sharer\Adapter;

use Base\Service\Model\Sharer\AbstractSharerAdapter;

class FacebookAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string
    {
        return "facebook";
    }
    public function getUrl(): string
    {
        return "https://www.facebook.com/sharer/sharer.php?quote={quote}&u={url}";
    }
    public static function __iconizeStatic(): ?array
    {
        return ["fa-brands fa-facebook", "fa-brands fa-facebook-square", "fa-brands fa-facebook-f", "fa-brands fa-facebook-messenger"];
    }
}
