<?php

namespace Base\Admin\Router;

class AdminUrlGenerator extends \EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator
{
    public function unset(string $key): self
    {
        if (isset($this->routeParameters[$key])) {
            unset($this->routeParameters[$key]);
        }

        // Remove filter keys
        foreach (array_keys($this->routeParameters) as $paramKey) {
            if (str_starts_with($paramKey, $key.'[')) {
                unset($this->routeParameters[$paramKey]);
            }
        }

        return $this;
    }

    public function generateUrl(): string
    {
        $url = \parse_url2(parent::generateUrl());
        if(array_key_exists("query", $url)) {

            $items = explode("&", urldecode($url["query"] ?? ""));
            foreach ($items as $i => $item) {
                [$key, ] = explode('=', $item, 2);

                if (!array_key_exists($key, $this->routeParameters)) {
                    unset($items[$i]);
                }
            }

            $items = array_values($items); // reindex
            $url["query"] = urlencode(implode("&", $items));
        }

        return compose_url(array_merge(\parse_url2(get_url()), $url));
    }
}
