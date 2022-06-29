<?php

namespace Base\Model\Sharer\Adapter;

use Base\Model\Sharer\AbstractSharerAdapter;

class LinkedInAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string { return "linkedin"; }
    public function getUrl(): string { return "https://www.linkedin.com/shareArticle?mini=true&title={title}&summary={text}&url={url}"; }
    public static function __iconizeStatic(): ?array
    {
        return ["fab fa-linkedin", "fab fa-linkedin-in"];
    }
}
