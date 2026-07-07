<?php

namespace Base\Service\Model\Sharing\Adapter;

use Base\Service\Model\Sharing\AbstractSharingAdapter;

class LinkedInAdapter extends AbstractSharingAdapter
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
        return ["fa-brands fa-linkedin", "fa-brands fa-linkedin-in"];
    }
}
