<?php

namespace Base\Admin\Config;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;

use Base\Admin\Factory\MenuFactory;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MainMenuDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

use Twig\Environment;

class Extension
{
    public const PAGE_DEFAULT = "default";
    public const PAGE_INDEX = Crud::PAGE_INDEX;
    public const PAGE_EDIT = Crud::PAGE_EDIT;
    public const PAGE_NEW = Crud::PAGE_NEW;

    /**
     * @var Environment
     */
    protected Environment $twig;

    /**
     * @var ControllerResolverInterface
     */
    protected ControllerResolverInterface $controllerResolver;
    
    /**
     * @var MenuFactory
     */
    protected MenuFactory $menuFactory;
    
    public function __construct(Environment $twig, ControllerResolverInterface $controllerResolver, MenuFactory $menuFactory)
    {
        $this->twig = $twig;
        $this->twig->addGlobal("ea_extra", $this);

        $this->controllerResolver = $controllerResolver;
        $this->menuFactory = $menuFactory;
    }

    public function createMainMenu(array $menuItems = []): MainMenuDto
    {
        return $this->menuFactory->createMainMenu($menuItems);
    }

    public function getController(string $controllerInterface, ?string $controllerFqcn, ?string $controllerAction, Request $request): ?object
    {
        if (null === $controllerFqcn || null === $controllerAction) {
            return null;
        }

        // needed to fix the double encoding of URLs that might happen (https://github.com/EasyCorp/EasyAdminBundle/pull/6902)
        $controllerFqcn = str_replace('%5C', '\\', $controllerFqcn);
        $newRequest = $request->duplicate(null, null, ['_controller' => [$controllerFqcn, $controllerAction]]);
        try {
            $controllerCallable = $this->controllerResolver->getController($newRequest);
        } catch (\InvalidArgumentException $e) {
            $controllerCallable = false;
        }

        if (false === $controllerCallable) {
            throw new NotFoundHttpException(sprintf('Unable to find the controller "%s::%s".', $controllerFqcn, $controllerAction));
        }

        if (!\is_array($controllerCallable)) {
            return null;
        }

        $controllerInstance = $controllerCallable[0];
        if (!\is_object($controllerInstance)) {
            return null;
        }

        return is_subclass_of($controllerInstance, $controllerInterface) ? $controllerInstance : null;
    }

    /**
     * @param string $varname
     * @param string|null $pageName
     * @return mixed|null
     */
    protected function getFallback(string $varname, ?string $pageName = null)
    {
        $pageName = $pageName ?? self::PAGE_DEFAULT;
        return $this->{$varname}[$pageName] ?? $this->{$varname}[self::PAGE_DEFAULT] ?? null;
    }

    protected array $title;

    public function getPageTitle(?string $pageName = null): ?string
    {
        return $this->getFallback("title", $pageName);
    }

    /**
     * @param $title
     * @param string|null $pageName
     * @return $this
     */
    public function setPageTitle($title, ?string $pageName = null)
    {
        return $this->setTitle($title, $pageName);
    }

    public function getTitle(?string $pageName = null): ?string
    {
        return $this->getFallback("title", $pageName);
    }

    /**
     * @param $title
     * @param string|null $pageName
     * @return $this
     */
    public function setTitle($title, ?string $pageName = null)
    {
        $this->title[$pageName ?? self::PAGE_DEFAULT] = $title;
        return $this;
    }

    protected array $logo;

    public function getLogo(?string $pageName = null): ?string
    {
        return $this->getFallback("logo", $pageName);
    }

    /**
     * @param string $logo
     * @param string|null $pageName
     * @return $this
     */
    public function setLogo(string $logo, ?string $pageName = null)
    {
        $this->logo[$pageName ?? self::PAGE_DEFAULT] = $logo;
        return $this;
    }

    protected array $help;

    public function getHelp(?string $pageName = null): ?string
    {
        return $this->getFallback("help", $pageName);
    }

    /**
     * @param string $help
     * @param string|null $pageName
     * @return $this
     */
    public function setHelp(string $help, ?string $pageName = null)
    {
        $this->help[$pageName ?? self::PAGE_DEFAULT] = $help;
        return $this;
    }

    protected array $text;

    public function getText(?string $pageName = null): ?string
    {
        return $this->getFallback("text", $pageName);
    }

    /**
     * @param string $text
     * @param string|null $pageName
     * @return $this
     */
    public function setText(string $text, ?string $pageName = null)
    {
        $this->text[$pageName ?? self::PAGE_DEFAULT] = $text;
        return $this;
    }

    protected array $icon;

    public function getIcon(?string $pageName = null): ?string
    {
        return $this->getFallback("icon", $pageName);
    }

    /**
     * @param string $icon
     * @param string|null $pageName
     * @return $this
     */
    public function setIcon(string $icon, ?string $pageName = null)
    {
        $this->icon[$pageName ?? self::PAGE_DEFAULT] = $icon;
        return $this;
    }

    protected array $image;
    protected array $imageAttributes;

    public function getImage(?string $pageName = null): ?string
    {
        return $this->getFallback("image", $pageName);
    }

    public function getImageAttributes(?string $pageName = null): ?array
    {
        return $this->getFallback("imageAttributes", $pageName);
    }

    /**
     * @param string|null $image
     * @param array $attrs
     * @param string|null $pageName
     * @return $this
     */
    public function setImage(?string $image, array $attrs = [], ?string $pageName = null)
    {
        $this->image[$pageName ?? self::PAGE_DEFAULT] = $image;
        $this->imageAttributes[$pageName ?? self::PAGE_DEFAULT] = $attrs;
        return $this;
    }

    protected array $widgets;

    public function getWidgets(?string $pageName = null): ?array
    {
        return $this->getFallback("widgets", $pageName);
    }

    /**
     * @param array $widgets
     * @param string|null $pageName
     * @return $this
     */
    public function setWidgets(array $widgets, ?string $pageName = null)
    {
        $this->widgets[$pageName ?? self::PAGE_DEFAULT] = $widgets;
        return $this;
    }

    /**
     * @param Dashboard $dashboard
     * @return Dashboard
     */
    public function configureDashboard(Dashboard $dashboard)
    {
        return $dashboard;
    }

    /**
     * @param Crud $crud
     * @return Crud
     */
    public function configureCrud(Crud $crud)
    {
        $actions = [self::PAGE_NEW, self::PAGE_EDIT, self::PAGE_INDEX];
        foreach ($actions as $action) {
            $crud->setPageTitle($action, $this->getPageTitle($action) ?? "");
            $crud->setHelp($action, $this->getHelp($action) ?? "");
        }

        return $crud;
    }
}
