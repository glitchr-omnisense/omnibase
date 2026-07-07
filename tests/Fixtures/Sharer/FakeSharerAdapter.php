<?php

namespace Tests\Base\Fixtures\Sharer;

use Base\Service\Model\Sharer\AbstractSharerAdapter;

class FakeSharerAdapter extends AbstractSharerAdapter
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
