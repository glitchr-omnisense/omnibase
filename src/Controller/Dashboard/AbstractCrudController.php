<?php

namespace Base\Controller\Dashboard;

use Base\Config\Extension;
use Base\Field\LinkIdField;
use Base\Service\BaseService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractCrudController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController
{
    abstract static function getPreferredIcon();

    public function __construct(Extension $extension, RequestStack $requestStack, TranslatorInterface $translator)
    {
        $this->requestStack = $requestStack;
        $this->extension = $extension;
        $this->translator = $translator;
    }

    public static function getEntityFqcn(): string
    {
        if(get_called_class() == "HyperpatternCrudController") dump("HEHO !");

        $entityFqcn = substr(get_called_class(), 0, -strlen("CrudController"));
        $entityFqcn = preg_replace('/\\\Controller\\\Dashboard\\\Crud\\\/', "\\Entity\\", $entityFqcn);

        if(str_starts_with($entityFqcn, "Base")) {

            $appEntityFqcn     = preg_replace("/^Base/", 'App', $entityFqcn);
            $appCrudController = preg_replace("/^Base/", 'App', get_called_class());
            if (!class_exists($appCrudController)) {

                self::$crudController[$appEntityFqcn] = get_called_class();
                return $appEntityFqcn;
            }
        }

        self::$crudController[$entityFqcn] = get_called_class();
        return $entityFqcn;
    }

    protected static array $crudController = [];
    public static function getCrudControllerFqcn($entityFqcn): ?string
    {
        if(array_key_exists($entityFqcn, self::$crudController))
            return self::$crudController[$entityFqcn];

        $crudController = preg_replace('/\\\Entity\\\/', "\\Controller\\\Dashboard\\\Crud\\", $entityFqcn);
        $crudController = $crudController . "CrudController";

        $appCrudController  = preg_replace("/^Base/", 'App', $crudController);
        $baseCrudController = preg_replace("/^App/", 'Base', $appCrudController);
        if(str_starts_with($crudController, "Base")) {

            if (class_exists($appCrudController) and !class_exists($crudController))
                return $appCrudController;
        }

        return  class_exists($appCrudController)  ? $appCrudController :
               (class_exists($baseCrudController) ? $baseCrudController : null);
    }

    public static function getTranslationPrefix()
    {
        $entityFqcn = preg_replace('/^(App|Base)\\\Entity\\\/', "Crud\\", self::getEntityFqcn());
        return camel_to_snake(str_replace("\\", ".", $entityFqcn));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
                ->setPermission(Action::NEW, 'ROLE_SUPERADMIN')
                ->add(Crud::PAGE_NEW,  Action::INDEX)
                ->setPermission(Action::EDIT, 'ROLE_SUPERADMIN')
                ->add(Crud::PAGE_EDIT, Action::INDEX)
                ->setPermission(Action::DELETE, 'ROLE_SUPERADMIN');
    }

    public function configureExtension(Extension $extension) : Extension
    { 
        return $extension;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $prefix = $this->getTranslationPrefix();
        $action = $this->requestStack->getCurrentRequest()->query->get("crudAction") ?? "";
        $prefixWithAction = $prefix . ($action ? "." . $action : "");

        $title = $this->translator->trans($prefixWithAction.".title", [], AbstractDashboardController::TRANSLATION_DOMAIN);
        if($title == $prefixWithAction.".title") $title = $this->translator->trans($prefix.".title", [], AbstractDashboardController::TRANSLATION_DOMAIN);
        if($title == $prefix.".title") $title = $this->translator->trans($prefix.".plural", [], AbstractDashboardController::TRANSLATION_DOMAIN);
        if($title == $prefix.".plural") $title = class_basename($this->getEntityFqcn());

        $help = $this->translator->trans($prefixWithAction.".help", [], AbstractDashboardController::TRANSLATION_DOMAIN);
        if($help == $prefixWithAction.".help") $help = $this->translator->trans($prefix.".help", [], AbstractDashboardController::TRANSLATION_DOMAIN);
        if($help == $prefix.".help") $help = "";

        $text = $this->translator->trans($prefixWithAction.".text", [], AbstractDashboardController::TRANSLATION_DOMAIN);
        if($text == $prefixWithAction.".text") $text = $this->translator->trans($prefix.".text", [], AbstractDashboardController::TRANSLATION_DOMAIN);
        if($text == $prefix.".text") $text = "";

        $this->extension->setIcon($this->getPreferredIcon());
        $this->extension->setTitle(ucfirst($title));
        $this->extension->setHelp($help);
        $this->extension->setText($text);

        $extension = $this->configureExtension($this->extension);
        return $extension->configureCrud($crud)
                ->showEntityActionsInlined(true)
                ->setDefaultSort(['id' => 'DESC'])
                ->setPaginatorPageSize(30)
                ->setFormOptions(
                    ['validation_groups' => ['new' ]],
                    ['validation_groups' => ['edit']]
                );
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield LinkIdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}
