<?php

namespace Base\Backend\Context;

use Base\Backend\Config\Extension;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetsDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\DashboardDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\I18nDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory;
use EasyCorp\Bundle\EasyAdminBundle\Registry\CrudControllerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Registry\TemplateRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminContext extends \EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext
{
    protected ?Extension $extension = null;

    public function __construct(Request $request, ?UserInterface $user, I18nDto $i18nDto, CrudControllerRegistry $crudControllers, DashboardDto $dashboardDto, DashboardControllerInterface $dashboardController, AssetsDto $assetDto, ?CrudDto $crudDto, ?EntityDto $entityDto, ?SearchDto $searchDto, MenuFactory $menuFactory, TemplateRegistry $templateRegistry, Extension $extension)
    {
        parent::__construct(
            $request, $user, $i18nDto, $crudControllers, $dashboardDto, $dashboardController,
            $assetDto, $crudDto, $entityDto, $searchDto, $menuFactory, $templateRegistry);

        $this->extension = $extension;
    }

    public function getExtension()
    {
         return $this->extension;
    }

    public function getTranslationDomain()
    {
        return $this->dashboardDto->getTranslationDomain();
    }

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
        $referenceUrl = compose_url ($referenceUrl["scheme"]  ?? null, $referenceUrl["user"]      ?? null, $referenceUrl["password"] ?? null,
                                        $referenceUrl["machine"] ?? null, $referenceUrl["subdomain"] ?? null, $referenceUrl["domain"]   ?? null, $referenceUrl["port"] ?? null,
                                        $referenceUrl["path"]    ?? null, $referenceUrl["query"]     ?? null);

        $url = parse_url($this->request->getRequestUri());
        $url["query"] ??= "";
        $url["query"] = explode_attributes("&", $url["query"]);
        $url["query"] = array_key_removes_startsWith($url["query"], ...$ignoredKeys);
        $url["query"] = array_key_exists("crudAction", $url["query"]) && in_array($url["query"]["crudAction"], ["index", "edit"]) ? array_key_removes($url["query"], "crudAction") : $url["query"];
        $url["query"] = array_map(fn($u) => urldecode($u), $url["query"]);
        ksort($url["query"]);

        $url["query"] = str_replace("\"", "", implode_attributes("&", $url["query"]));
        $url = compose_url ($url["scheme"]  ?? null, $url["user"]      ?? null, $url["password"] ?? null,
                            $url["machine"] ?? null, $url["subdomain"] ?? null, $url["domain"]   ?? null, $url["port"] ?? null,
                            $url["path"]    ?? null, $url["query"]     ?? null);

        return $url == $referenceUrl;
    }

}
