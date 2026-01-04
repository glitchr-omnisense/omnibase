<?php

namespace Base\Admin\Provider;

class AdminContextProvider extends \EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider
{
    public function getTranslationDomain()
    {
        return $this->getContext(true)->getTranslationDomain();
    }
}
