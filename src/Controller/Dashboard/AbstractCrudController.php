<?php

namespace Base\Controller\Dashboard;

use Base\Config\Extension;
use Base\Field\IdField;
use Base\Model\IconizeInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
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
        
        $this->crud = null;
        $this->entity = null;
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

    public static function getCrudTranslationPrefix() { return "@".AbstractDashboardController::TRANSLATION_DASHBOARD.".".self::getTranslationPrefix("Crud\\"); }
    public static function getEntityTranslationPrefix() { return "@".AbstractDashboardController::TRANSLATION_ENTITY.".".self::getTranslationPrefix(); }
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

    public function getCrud():Crud { return $this->crud; }
    public function getEntityDto():EntityDto { return $this->entityDto; }
    public function getEntity() { return $this->entityDto ? $this->entityDto->getInstance() : null; }
    public function getExtension():Extension { return $this->extension; }
    
    public function configureCrud(Crud $crud): Crud
    {
        $this->crud = $crud;

        $entityTranslationPrefix = $this->getEntityTranslationPrefix();
        
        $entityLabelInSingular = $this->translator->trans($entityTranslationPrefix.".singular", [], AbstractDashboardController::TRANSLATION_ENTITY);
        if($entityLabelInSingular == $entityTranslationPrefix.".singular") $entityLabelInSingular = null;
        $crud->getAsDto()->setEntityLabelInSingular($entityLabelInSingular ?? "");
        
        $entityLabelInPlural = $this->translator->trans($entityTranslationPrefix.".plural", [], AbstractDashboardController::TRANSLATION_ENTITY);
        if($entityLabelInPlural == $entityTranslationPrefix.".plural") $entityLabelInPlural = null;
        $crud->getAsDto()->setEntityLabelInPlural($entityLabelInPlural ?? "");

        $crudTranslationPrefix = $this->getCrudTranslationPrefix();
        $action = $this->requestStack->getCurrentRequest()->query->get("crudAction") ?? "";
        $crudTranslationPrefixWithAction = $crudTranslationPrefix . ($action ? "." . $action : "");

        $title = $this->translator->trans($crudTranslationPrefixWithAction.".title");
        if($title == $crudTranslationPrefixWithAction.".title") $title = $this->translator->trans($crudTranslationPrefix.".title");
        if($title == $crudTranslationPrefix.".title") $title = $this->translator->trans($crudTranslationPrefix.".plural");
        if($title == $crudTranslationPrefix.".plural") $title = $entityLabelInPlural ?? class_basename($this->getEntityFqcn());

        $help = $this->translator->trans($crudTranslationPrefixWithAction.".help");
        if($help == $crudTranslationPrefixWithAction.".help") $help = $this->translator->trans($crudTranslationPrefix.".help");
        if($help == $crudTranslationPrefix.".help") $help = "";

        $text = $this->translator->trans($crudTranslationPrefixWithAction.".text");
        if($text == $crudTranslationPrefixWithAction.".text") $text = $this->translator->trans($crudTranslationPrefix.".text");
        if($text == $crudTranslationPrefix.".text") $text = "";

        $icon = class_implements_interface($this->getEntityFqcn(), IconizeInterface::class) ? $this->getEntityFqcn()::__staticIconize()[0] : null;
        $icon = $this->getPreferredIcon() ?? $icon ?? "fas fa-question-circle";

        $this->extension->setIcon($icon);
        $this->extension->setTitle(ucfirst($title));
        $this->extension->setHelp($help);
        $this->extension->setText($text);
        
        return $this->configureExtension($this->extension)
                    ->configureCrud($crud)
                    ->showEntityActionsInlined(true)
                    ->setDefaultSort(['id' => 'DESC'])
                    ->setPaginatorPageSize(30)
                    ->setFormOptions(
                        ['validation_groups' => ['new' ]],
                        ['validation_groups' => ['edit']]
                    );
    }

    public function configureExtensionWithResponseParameters(Extension $extension, KeyValueStore $responseParameters): Extension
    {
        if($entity = $this->getEntity()) {

            $userClass = "user.".strtolower(camel_to_snake(class_basename($entity)));
            $entityLabel = $this->translator->trans($userClass.".singular", [], AbstractDashboardController::TRANSLATION_ENTITY);
            if($entityLabel == $userClass.".singular") $entityLabel = null;
            else $extension->setTitle(ucwords($entityLabel));

            $entityLabel = $entityLabel ?? $this->getCrud()->getAsDto()->getEntityLabelInSingular() ?? "";
            $entityLabel = !empty($entityLabel) ? ucwords($entityLabel) : "";

            $entityTitle = $entity->getTitle();
            if($entityTitle) $entityText  = $entityLabel ." ID #".$entity->getId();
            else {

                $entityTitle = $entityLabel ?? $this->getCrud()->getAsDto()->getEntityLabelInSingular() ?? "";
                $entityText  = "ID #".$entity->getId();
            }

            $extension->setTitle($entityTitle);
            $extension->setText($entityText); 
        }

        return $extension;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $this->entityDto = $responseParameters->get("entity");

        $this->extension = $this->configureExtensionWithResponseParameters($this->extension, $responseParameters);
        return parent::configureResponseParameters($responseParameters);
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        
        foreach ( ($callbacks[""] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield IdField::new('id')->hideOnForm()->hideOnDetail();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}
