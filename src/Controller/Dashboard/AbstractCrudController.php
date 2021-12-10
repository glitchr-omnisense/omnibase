<?php

namespace Base\Controller\Dashboard;

use Base\Config\Extension;
use Base\Field\IdField;
use Base\Model\IconizeInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractCrudController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController
{
    abstract static function getPreferredIcon(): ?string;

    public function __construct(EntityManagerInterface $entityManager, Extension $extension, RequestStack $requestStack, TranslatorInterface $translator)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->extension = $extension;
        $this->translator = $translator;
    }

    public static function getEntityFqcn(): string
    {
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

    public static function getCrudTranslationPrefix() { return self::getTranslationPrefix("Crud\\"); }
    public static function getEntityTranslationPrefix() { return self::getTranslationPrefix(); }
    public static function getTranslationPrefix(?string $prefix = "")
    {
        $entityFqcn = preg_replace('/^(App|Base)\\\Entity\\\/', $prefix ?? "", self::getEntityFqcn());
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
        
        $action = $this->requestStack->getCurrentRequest()->query->get("crudAction") ?? "";
        $crudTranslationPrefix = $this->getCrudTranslationPrefix();
        $crudTranslationPrefixWithAction = $crudTranslationPrefix . ($action ? "." . $action : "");

        $entityTranslationPrefix = $this->getEntityTranslationPrefix();

        $title = $this->translator->trans($crudTranslationPrefixWithAction.".title", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        if($title == $crudTranslationPrefixWithAction.".title") $title = $this->translator->trans($crudTranslationPrefix.".title", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        if($title == $crudTranslationPrefix.".title") $title = $this->translator->trans($crudTranslationPrefix.".plural", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        if($title == $crudTranslationPrefix.".plural") $title = $this->translator->trans($entityTranslationPrefix.".title", [], AbstractDashboardController::TRANSLATION_ENTITY);
        if($title == $entityTranslationPrefix.".plural") $title = class_basename($this->getEntityFqcn());

        $help = $this->translator->trans($crudTranslationPrefixWithAction.".help", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        if($help == $crudTranslationPrefixWithAction.".help") $help = $this->translator->trans($crudTranslationPrefix.".help", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        if($help == $crudTranslationPrefix.".help") $help = "";

        $text = $this->translator->trans($crudTranslationPrefixWithAction.".text", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        if($text == $crudTranslationPrefixWithAction.".text") $text = $this->translator->trans($crudTranslationPrefix.".text", [], AbstractDashboardController::TRANSLATION_DASHBOARD);
        if($text == $crudTranslationPrefix.".text") $text = "";

        $icon = class_implements_interface($this->getEntityFqcn(), IconizeInterface::class) ? $this->getEntityFqcn()::__staticIconize()[0] : null;
        $icon = $this->getPreferredIcon() ?? $icon ?? "fas fa-question-circle";

        $this->extension->setIcon($icon);
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
        
        foreach ( ($callbacks[""] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield IdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}
