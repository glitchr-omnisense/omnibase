<?php

namespace Base\Exception;

use Exception;

class SitemapNotFoundException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'No sitemap annotation found for this route.';
    }
}
