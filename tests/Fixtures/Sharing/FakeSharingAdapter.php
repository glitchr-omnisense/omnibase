<?php

namespace Tests\Base\Fixtures\Sharing;

use Base\Service\Model\Sharing\AbstractSharingAdapter;

class FakeSharingAdapter extends AbstractSharingAdapter
{
    public function getIdentifier(): string
    {
        return "fake";
    }

    public function getUrl(): string
    {
        return "https://share.example/create?a={a}&b={b}&url={url}";
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-share"];
    }
}
