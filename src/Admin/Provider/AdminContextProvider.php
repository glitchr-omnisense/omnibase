<?php

namespace Base\Admin\Provider;

readonly class AdminContextProvider extends \EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider
{
    public function getTranslationDomain()
    {
        return $this->getContext(true)->getTranslationDomain();
    }
}
