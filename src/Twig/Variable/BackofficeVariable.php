<?php

namespace Base\Twig\Variable;

use Base\Controller\Backend\AbstractDashboardController;

class BackofficeVariable extends SiteVariable
{
    public function title()  
    { 
        return $this->baseService->getBackoffice()["title"] 
            ?? $this->translator->trans("backoffice.title", [], AbstractDashboardController::TRANSLATION_DASHBOARD)  
            ?? parent::title();
    }

    public function slogan() 
    { 
        return $this->baseService->getBackoffice()["slogan"] 
            ?? $this->translator->trans("backoffice.slogan", [], AbstractDashboardController::TRANSLATION_DASHBOARD)  
            ?? parent::title(); 
    }

    public function logo()   
    { 
        return $this->baseService->getBackoffice()["logo"] 
            ?? parent::logo();
    }
}
