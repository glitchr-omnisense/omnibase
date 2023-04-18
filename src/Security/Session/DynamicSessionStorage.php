<?php

namespace Base\Security\Session;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class DynamicSessionStorage extends NativeSessionStorage
{
     /**
     * setOptions.
     *
     * {@inheritDoc}
     */
    public function setOptions(array $options)
    {
        if(isset($_SERVER['HTTP_HOST'])) {

            $domain = parse_url2($_SERVER['HTTP_HOST'])["domain"] ?? null;
            if ($domain) {

                $validDomains = json_decode($_ENV['HTTP_DOMAIN']) ?? $_ENV["HTTP_DOMAIN"];
                $validDomains = is_array($validDomains) ? $validDomains : array_filter([$validDomains]);
                if (!empty($validDomains) && in_array($domain, $validDomains))
                    $options["cookie_domain"] = $domain;

            }
        }

        return parent::setOptions($options);
    }
}