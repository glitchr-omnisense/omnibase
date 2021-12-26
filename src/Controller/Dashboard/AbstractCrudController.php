<?php

namespace Base\Controller\Dashboard;

use Base\Config\Extension;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Field\IdField;
use Base\Model\IconizeInterface;
use Base\Service\BaseSettings;
use Doctrine\ORM\EntityManagerInterface;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;

use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ActionFactory;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractCrudController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController
{
    abstract static function getPreferredIcon(): ?string;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    /**
     * @var ActionFactory
     */
    protected $actionFactory;

    public function __construct(ActionFactory $actionFactory, ClassMetadataManipulator $classMetadataManipulator, BaseSettings $baseSettings, EntityManagerInterface $entityManager, Extension $extension, RequestStack $requestStack, TranslatorInterface $translator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->actionFactory = $actionFactory;
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->extension = $extension;
        $this->translator = $translator;
        $this->baseSettings = $baseSettings;
        
        $this->crud = null;
        $this->entity = null;
    }

    public static function getEntityFqcn(): string
    {
        $entityFqcn = substr(get_called_class(), 0, -strlen("CrudController"));
        $entityFqcn = get_alias(preg_replace('/\\\Controller\\\Dashboard\\\Crud\\\/', "\\Entity\\", $entityFqcn));
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
        if( false !== ($pos = strrpos($crudController, '\\__CG__\\')) ) 
            $crudController = substr($crudController, $pos + 8);

        $appCrudController  = preg_replace("/^Base/", 'App', $crudController);
        $baseCrudController = preg_replace("/^App/", 'Base', $appCrudController);
        if(str_starts_with($crudController, "Base")) {

            if (class_exists($appCrudController) and !class_exists($crudController))
                return $appCrudController;
        }

        return (class_exists($appCrudController)  ? $appCrudController :
               (class_exists($baseCrudController) ? $baseCrudController : null));
    }

    public static function getCrudTranslationPrefix() { return "@".AbstractDashboardController::TRANSLATION_DASHBOARD.".".self::getTranslationPrefix("Crud\\"); }
    public static function getEntityTranslationPrefix() { return "@".AbstractDashboardController::TRANSLATION_ENTITY.".".self::getTranslationPrefix(); }
    public static function getTranslationPrefix(?string $prefix = "")
    {
        $entityFqcn = preg_replace('/^(App|Base)\\\Entity\\\/', $prefix ?? "", self::getEntityFqcn());
        return camel_to_snake(str_replace("\\", ".", $entityFqcn));
    }

    function setDiscriminatorMapAttribute(Action $action)
    {
        $entity     = get_alias($this->getEntityFqcn());
        $rootEntity = get_alias($this->classMetadataManipulator->getRootEntityName($entity));
        $actionDto = $action->getAsDto();

        if($entity == $rootEntity) {

            $htmlAttributes        = $actionDto->getHtmlAttributes();
            $htmlAttributes["root-entity"] = urlencode($this->getCrudControllerFqcn($rootEntity));
            $htmlAttributes["map"] = [];

            foreach($this->classMetadataManipulator->getDiscriminatorMap($entity) as $key => $class) {

                $class = get_alias($class);
                if($class == $rootEntity) continue;

                $k     = explode("_", $key);
                $key   = array_shift($k);

                if(( $crudClassController = $this->getCrudControllerFqcn($class) )) {
                    $array = [implode("_", $k) => urlencode($crudClassController)];
                    $htmlAttributes["map"][$key] = array_merge($htmlAttributes["map"][$key] ?? [], $array);
                }
            }

            if(!empty($htmlAttributes["map"])) {

                $actionDto->setHtmlElement("discriminator");
                $actionDto->setHtmlAttributes($htmlAttributes);
            }
        }


        return $action;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
                ->update(Crud::PAGE_INDEX, Action::NEW   , fn(Action $a) => $this->setDiscriminatorMapAttribute($a))
                ->setPermission(Action::NEW, 'ROLE_SUPERADMIN')
                ->setPermission(Action::EDIT, 'ROLE_SUPERADMIN')
                ->setPermission(Action::DELETE, 'ROLE_SUPERADMIN');
    }

    public function configureExtension(Extension $extension) : Extension
    { 
        return $extension;
    }

    public function getExtension():Extension { return $this->extension; }

    public function getCrud():Crud { return $this->crud; }
    public function getEntity() { return $this->entityDto ? $this->entityDto->getInstance() : null; }
    public function getEntityDto():EntityDto { return $this->entityDto; }

    public static function getEntityLabelInSingular() { return self::getEntityTranslationPrefix().".singular"; }
    public static function getEntityLabelInPlural() { return self::getEntityTranslationPrefix().".plural"; }
    public static function getEntityIcon()
    {
        $icon = get_called_class()::getPreferredIcon() ?? null;
        return $icon ?? class_implements_interface(self::getEntityFqcn(), IconizeInterface::class) ? self::getEntityFqcn()::__staticIconize()[0] : null;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $this->crud = $crud;

        // Extra information configuration
        $entityLabelInSingular = $this->translator->trans(self::getEntityLabelInSingular());
        if($entityLabelInSingular == self::getEntityLabelInSingular()) $entityLabelInSingular = null;
        $crud->getAsDto()->setEntityLabelInSingular($entityLabelInSingular ?? "");
        
        $entityLabelInPlural = $this->translator->trans(self::getEntityLabelInPlural());
        if($entityLabelInPlural == self::getEntityLabelInPlural()) $entityLabelInPlural = null;
        $crud->getAsDto()->setEntityLabelInPlural($entityLabelInPlural ?? "");

        $crudTranslationPrefix = $this->getCrudTranslationPrefix();
        $action = $this->requestStack->getCurrentRequest()->query->get("crudAction") ?? "";
        $crudTranslationPrefixWithAction = $crudTranslationPrefix . ($action ? "." . $action : "");

        $title = $this->translator->trans($crudTranslationPrefixWithAction.".title");
        if($title == $crudTranslationPrefixWithAction.".title") $title = $this->translator->trans($crudTranslationPrefix.".title");
        if($title == $crudTranslationPrefix.".title") $title = $this->translator->trans($crudTranslationPrefix.".plural");
        if($title == $crudTranslationPrefix.".plural") $title = $entityLabelInPlural ?? camel_to_snake(class_basename($this->getEntityFqcn()), " ");

        $help = $this->translator->trans($crudTranslationPrefixWithAction.".help");
        if($help == $crudTranslationPrefixWithAction.".help") $help = $this->translator->trans($crudTranslationPrefix.".help");
        if($help == $crudTranslationPrefix.".help") $help = "";

        $text = $this->translator->trans($crudTranslationPrefixWithAction.".text");
        if($text == $crudTranslationPrefixWithAction.".text") $text = $this->translator->trans($crudTranslationPrefix.".text");
        if($text == $crudTranslationPrefix.".text") $text = "";

        $this->extension->setIcon($this->getEntityIcon() ?? "fas fa-question-circle");
        $this->extension->setTitle(mb_ucfirst($title));
        $this->extension->setHelp($help);
        $this->extension->setText($text);

        // Configure CRUD and extension
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
        $entity = $this->getEntity();
        if(!$entity) return $extension;
        
        $userClass = "user.".mb_strtolower(camel_to_snake(class_basename($entity)));
        $entityLabel = $this->translator->trans($userClass.".singular", [], AbstractDashboardController::TRANSLATION_ENTITY);
        if($entityLabel == $userClass.".singular") $entityLabel = null;
        else $extension->setTitle(mb_ucwords($entityLabel));

        $entityLabel = $entityLabel ?? $this->getEntityLabelInSingular() ?? "";
        $entityLabel = !empty($entityLabel) ? mb_ucwords($entityLabel) : "";

        $entityTitle = null;
        if ($entity) {

            $class = str_replace(["App\\", "Base\\Entity\\"], ["Base\\", ""], get_class($entity));
            $class = implode(".", array_map("camel_to_snake", explode("\\", $class)));
            $entityLabel = mb_ucwords($this->translator->trans(mb_strtolower(camel_to_snake($class)).".singular", [], AbstractDashboardController::TRANSLATION_ENTITY));
        }

        $entityTitle = $entityLabel ?? $this->getCrud()->getAsDto()->getEntityLabelInSingular() ?? "";
        $extension->setTitle($entityTitle);

        if($this->getCrud()->getAsDto()->getCurrentAction() != "new") {
            $entityText = $entityLabel ." ID #".$entity->getId();
            $extension->setText($entityText); 
        }

        return $extension;
    }

    public function configureActionsWithResponseParameters(Actions $actions, KeyValueStore $responseParameters): Extension
    {
        return $actions;
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
