<?php

namespace Base\Admin\Context;

use Base\Admin\Config\Extension;
use Base\Controller\Admin\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MainMenuDto;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;

class AdminContext extends \EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext
{
    protected ?Extension $extension = null;
    protected ?AbstractDashboardController $dashboardController = null;

    public function __construct(...$args)
    {
        $this->extension = \array_pop_class(Extension::class, $args);
        parent::__construct(...$args);

        $this->dashboardController = $this->extension->getController(
            AbstractDashboardController::class,
            $this->getDashboardControllerFqcn(),
            "index",
            $this->getRequest()
        );
    }

    /**
     * @return Extension|null
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @return string
     */
    public function getTranslationDomain()
    {
        return $this->dashboardDto->getTranslationDomain() ?? EA::DEFAULT_TRANSLATION_DOMAIN;
    }

    public function impersonator_permission(): string
    {
        return $this->getImpersonatorPermission();
    }

    public function getImpersonatorPermission(): string
    {
        return class_exists(AuthenticatedVoter::class) ? AuthenticatedVoter::IS_IMPERSONATOR : 'ROLE_PREVIOUS_ADMIN';
    }

    /**
     * @var MainMenuDto
     */
    protected ?MainMenuDto $mainMenuBeforeDto = null;
    public function getMainMenuBefore(): ?MainMenuDto
    {
        if (null !== $this->mainMenuBeforeDto) {
            return $this->mainMenuBeforeDto;
        }
 
        $configuredMenuItems = $this->dashboardController->configureMenuBeforeItems();
        $mainMenuItems = \is_array($configuredMenuItems) ? $configuredMenuItems : iterator_to_array($configuredMenuItems, false);
    
        return $this->mainMenuBeforeDto = $this->extension->createMainMenu($mainMenuItems);
    }

    protected ?MainMenuDto $mainMenuDto = null;
    public function getMainMenu(): MainMenuDto
    {
        if (null !== $this->mainMenuDto) {
            return $this->mainMenuDto;
        }

        $configuredMenuItems = $this->dashboardController->configureMenuItems();
        $mainMenuItems = \is_array($configuredMenuItems) ? $configuredMenuItems : iterator_to_array($configuredMenuItems, false);

        return $this->mainMenuDto = $this->extension->createMainMenu($mainMenuItems);
    }

    /**
     * @var MainMenuDto
     */
    protected ?MainMenuDto $mainMenuAfterDto = null;
    public function getMainMenuAfter(): ?MainMenuDto
    {
        if (null !== $this->mainMenuAfterDto) {
            return $this->mainMenuAfterDto;
        }

        $configuredMenuItems = $this->dashboardController->configureMenuAfterItems();
        $mainMenuItems = \is_array($configuredMenuItems) ? $configuredMenuItems : iterator_to_array($configuredMenuItems, false);

        return $this->mainMenuAfterDto = $this->extension->createMainMenu($mainMenuItems);
    }

    /**
     * @param string $referenceUrl
     * @param array $ignoredKeys
     * @return bool
     */
    public function isActive(string $referenceUrl, array $ignoredKeys = ["menuIndex", "submenuIndex", "filters[", "page", "sort[", "entityId", "referrer"])
    {
        $referenceUrl = parse_url($referenceUrl);
        $referenceUrl["query"] ??= "";
        $referenceUrl["query"] = explode_attributes("&", $referenceUrl["query"]);
        $referenceUrl["query"] = array_key_removes_startsWith($referenceUrl["query"], true, ...$ignoredKeys);
        $referenceUrl["query"] = array_key_exists("crudAction", $referenceUrl["query"]) && in_array($referenceUrl["query"]["crudAction"], ["index", "edit"]) ? array_key_removes($referenceUrl["query"], "crudAction") : $referenceUrl["query"];
        $referenceUrl["query"] = array_map(fn($u) => urldecode($u), $referenceUrl["query"]);
        ksort($referenceUrl["query"]);

        $referenceUrl["query"] = str_replace("\"", "", implode_attributes("&", $referenceUrl["query"]));
        $referenceUrl = compose_url(
            $referenceUrl["scheme"] ?? null,
            $referenceUrl["user"] ?? null,
            $referenceUrl["password"] ?? null,
            $referenceUrl["machine"] ?? null,
            $referenceUrl["subdomain"] ?? null,
            $referenceUrl["domain"] ?? null,
            $referenceUrl["port"] ?? null,
            $referenceUrl["path"] ?? null,
            $referenceUrl["query"] ?? null,
            $referenceUrl["fragment"] ?? null
        );

        $url = parse_url($this->getRequest()->getRequestUri());

        $url["query"] ??= "";
        $url["query"] = explode_attributes("&", $url["query"]);
        $url["query"] = array_key_removes_startsWith($url["query"], ...$ignoredKeys);
        $url["query"] = array_key_exists("crudAction", $url["query"]) && in_array($url["query"]["crudAction"], ["index", "edit"]) ? array_key_removes($url["query"], "crudAction") : $url["query"];
        $url["query"] = array_map(fn($u) => urldecode($u), $url["query"]);
        ksort($url["query"]);

        $url["query"] = str_replace("\"", "", implode_attributes("&", $url["query"]));
        $url = compose_url(
            $url["scheme"] ?? null,
            $url["user"] ?? null,
            $url["password"] ?? null,
            $url["machine"] ?? null,
            $url["subdomain"] ?? null,
            $url["domain"] ?? null,
            $url["port"] ?? null,
            $url["path"] ?? null,
            $url["query"] ?? null,
            $url["fragment"] ?? null
        );

        return $url == $referenceUrl;
    }
}
