<?php

namespace Base\Service\Model\Sharing\Adapter;

use Base\Service\Model\Sharing\AbstractSharingAdapter;

class FacebookAdapter extends AbstractSharingAdapter
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
