<?php

namespace Base\Controller\Dashboard;

use Base\Config\Extension;
use Base\Database\Factory\ClassMetadataManipulator;

use Base\Field\IdField;
use Base\Model\IconizeInterface;
use Base\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\ActionCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractCrudController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController
{
    abstract static function getPreferredIcon(): ?string;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(
        AdminUrlGenerator $adminUrlGenerator,
        ClassMetadataManipulator $classMetadataManipulator, 
        EntityManagerInterface $entityManager, 
        RequestStack $requestStack,
        Extension $extension, 
        TranslatorInterface $translator, BaseService $baseService)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->extension = $extension;
        $this->translator = $translator;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->baseService = $baseService;
        
        $this->crud = null;
    }

    public static function getEntityFqcn(): string
    {
        $entityFqcn = substr(get_called_class(), 0, -strlen("CrudController"));
        $entityFqcn = get_alias(preg_replace('/\\\Controller\\\Dashboard\\\Crud\\\/', "\\Entity\\", $entityFqcn));
        self::$crudController[$entityFqcn] = get_called_class();
        return $entityFqcn;
    }

    protected static array $crudController = [];
    public static function getCrudControllerFqcn($entity, bool $inheritance = false): ?string
    {
        $entityFqcn = is_object($entity) ? get_class($entity) : (class_exists($entity) ? $entity : null);
        if($entityFqcn === null) return null;

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
               (class_exists($baseCrudController) ? $baseCrudController : ($inheritance ? self::getCrudControllerFqcn(get_parent_class($entity)) : null)));
    }

    public static function getCrudTranslationPrefix()   { return "@".AbstractDashboardController::TRANSLATION_DASHBOARD.".".self::getTranslationPrefix("Crud\\"); }
    public static function getEntityTranslationPrefix() { return "@".AbstractDashboardController::TRANSLATION_ENTITY   .".".self::getTranslationPrefix(); }
    public static function getTranslationPrefix(?string $prefix = "")
    {
        $entityFqcn = preg_replace('/^(App|Base)\\\Entity\\\/', $prefix ?? "", self::getEntityFqcn());
        return camel_to_snake(str_replace("\\", ".", $entityFqcn));
    }

    public function setDiscriminatorMapAttribute(Action $action)
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
                ->update(Crud::PAGE_INDEX, Action::NEW ,    fn(Action $a) => $this->setDiscriminatorMapAttribute($a))
                ->setPermission(Action::NEW, 'ROLE_EDITOR')
                ->setPermission(Action::EDIT, 'ROLE_EDITOR')
                ->setPermission(Action::DELETE, 'ROLE_EDITOR');
    }

    public function configureExtension(Extension $extension) : Extension
    { 
        return $extension;
    }

    public function getExtension():Extension { return $this->extension; }

    protected $crud = null;

    public function getCrud():Crud { return $this->crud; }

    protected $entityDto = null;
    public function getEntity() { return $this->entityDto ? $this->entityDto->getInstance() : null; }
    public function getEntityDto():EntityDto { return $this->entityDto; }
    public function getEntityCollection():EntityCollection { return $this->entityCollection; }

    public static function getEntityLabelInSingular() { return self::getEntityTranslationPrefix().".singular"; }
    public static function getEntityLabelInPlural() { return self::getEntityTranslationPrefix().".plural"; }
    public static function getEntityIcon()
    {
        $icon = get_called_class()::getPreferredIcon() ?? null;
        return $icon ?? class_implements_interface(self::getEntityFqcn(), IconizeInterface::class) ? self::getEntityFqcn()::__iconizeStatic()[0] : null;
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
    
    public function configureEntityCollectionWithResponseParameters(?EntityCollection $entityCollection, KeyValueStore $responseParameters): ?EntityCollection
    {
        foreach($entityCollection ?? [] as $entityDto) {

            $entityDto = $this->configureEntityDto($entityDto);
            $actions = $entityDto->getActions();
            foreach($actions ?? [] as $action) {

                $instance = $entityDto->getInstance();
                $crudController = $this->getCrudControllerFqcn($instance);

                $discriminatorValue = $this->classMetadataManipulator->getDiscriminatorValue($instance);
                if($crudController && $discriminatorValue) {

                    $url = $this->adminUrlGenerator
                            ->unsetAll()
                            ->setController($crudController)
                            ->setEntityId($instance->getId())
                            ->setAction($action->getName())
                            ->includeReferrer()
                            ->generateUrl();

                    $action->setLinkUrl($url);
                }
            }

            $actions = $this->configureActionsWithEntityDto($actions, $entityDto);
        }

        return $entityCollection;
    }

    public function configureExtensionWithResponseParameters(Extension $extension, KeyValueStore $responseParameters): Extension
    {
        $entity = $this->getEntity();
        if(!$entity) return $extension;
        
        $userClass = "user.".mb_strtolower(camel_to_snake(class_basename($entity)));
        $entityLabel = $this->translator->trans($userClass.".plural", [], AbstractDashboardController::TRANSLATION_ENTITY);
        if($entityLabel == $userClass.".plural") $entityLabel = null;
        else $extension->setTitle(mb_ucfirst($entityLabel));
        
        $entityLabel = $entityLabel ?? $this->getEntityLabelInSingular() ?? "";
        $entityLabel = !empty($entityLabel) ? mb_ucfirst($entityLabel) : "";

        if ($entity) {

            $class = str_replace(["App\\", "Base\\Entity\\"], ["Base\\", ""], get_class($entity));
            $class = implode(".", array_map("camel_to_snake", explode("\\", $class)));
            $entityLabel = mb_ucfirst($this->translator->trans(mb_strtolower(camel_to_snake($class)).".singular", [], AbstractDashboardController::TRANSLATION_ENTITY));

            $extension->setTitle(mb_ucfirst($this->translator->trans(mb_strtolower(camel_to_snake($class)).".plural", [], AbstractDashboardController::TRANSLATION_ENTITY)));
        }

        if($this->getCrud()->getAsDto()->getCurrentAction() != "new") {
            $entityText = $entityLabel ." ID #".$entity->getId();
            $extension->setText($entityText); 
        }

        return $extension;
    }

    public function configureActionsWithEntityDto(ActionCollection $actions, EntityDto $entityDto): ActionCollection
    {
        return $actions;
    }

    public function configureEntityDto(EntityDto $entityDto): EntityDto
    {
        return $entityDto;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $this->entityDto        = $responseParameters->get("entity");

        $this->entityCollection = $responseParameters->get("entities");
        $this->entityCollection = $this->configureEntityCollectionWithResponseParameters(
            $this->entityCollection, 
            $responseParameters
        );

        $this->entityCollection = $responseParameters->set("entities", $this->entityCollection);
        if($this->entityCollection)
            $this->responseParameters->set("entities", $this->entityCollection);

        $this->extension = $this->configureExtensionWithResponseParameters($this->extension, $responseParameters);
        return parent::configureResponseParameters($responseParameters);
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        array_prepend($args, fn() => yield IdField::new());
        
        $yields = $this->yield($args);
        $simpleYields      = array_filter_recursive(array_filter($yields, fn($k) => preg_match("/^[0-9.]+$/", $k), ARRAY_FILTER_USE_KEY));
        $associativeYields = array_diff_key($yields, $simpleYields);

        $simpleYields      = array_values($simpleYields);
        foreach ($simpleYields as $_) foreach($_ as $yield) {

            yield $yield;

            $property = $yield->getAsDto()->getProperty();
            foreach($associativeYields as $path => $_) {

                if($property != preg_replace("/^[0-9.]+/", "",$path))
                    continue;

                foreach($_ ?? [] as $yield) yield $yield;
                unset($associativeYields[$path]);
            }
        }
        
        foreach($associativeYields as $_) foreach($_ ?? [] as $yield) 
            yield $yield;
    }

    public function yield(array &$args, ?string $field = null): array
    {
        $args = array_map_recursive(
                fn($v) => is_callable($v) ? $v() : $v,
                array_flatten(array_filter_recursive($args,
                    fn($v, $k) => ($field === null || $k == $field) && !empty($v),
                    ARRAY_FILTER_USE_BOTH)
                , ARRAY_FLATTEN_PRESERVE_KEYS)
            );

        $yields = [];
        foreach($args as $field2 => $yield)
            if($field === null || $field == $field2) $yields[$field2] = $yield;

        return $yields;
    }
}
