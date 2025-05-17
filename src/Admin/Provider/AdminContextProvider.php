<?php

namespace Base\Admin\Provider;

use Base\Admin\Config\Extension;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;

class AdminContextProvider extends \EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider
{
    public function getTranslationDomain()
    {
        return $this->getContext(true)->getTranslationDomain();
    }
}
