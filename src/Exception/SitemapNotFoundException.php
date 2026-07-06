<?php

namespace Base\Exception;

use Exception;

class SitemapNotFoundException extends Exception
{
    /**
     * {}
     */
    public function getMessageKey()
    {
        return 'No sitemap attribute found for this route.';
    }
}
