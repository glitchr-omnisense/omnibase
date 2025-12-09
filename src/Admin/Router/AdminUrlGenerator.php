<?php

namespace Base\Admin\Router;

class AdminUrlGenerator extends \EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator
{
    public function generateUrl(): string
    {
        $url = parent::generateUrl();
        $url = compose_url(array_merge(
            \parse_url2(get_url()), 
            \parse_url2($url))
        );

        return $url;
    }
}
