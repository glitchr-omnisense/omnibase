<?php

namespace Base\Service\Model;

/**
 *
 */
interface HtmlizeInterface
{
    public function __toHtml(array $options = [], ...$args): ?string;
}
