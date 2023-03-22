<?php

namespace Base\Service\Model\Sharer\Adapter;

use Base\Service\Model\Sharer\AbstractSharerAdapter;

class LinkedInAdapter extends AbstractSharerAdapter
{
    public function getIdentifier(): string
    {
        return "linkedin";
    }
    public function getUrl(): string
    {
        return "https://www.linkedin.com/shareArticle?mini=true&title={title}&summary={summary}&url={url}";
    }
    public static function __iconizeStatic(): ?array
    {
        return ["fab fa-linkedin", "fab fa-linkedin-in"];
    }
}
