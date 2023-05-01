<?php

namespace Base\Controller\Backend;

use Base\Backend\Config\Extension;
use Base\BaseBundle;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Entity\Layout\Widget\Link;
use Base\Field\IdField;
use Base\Service\FileService;
use Base\Service\Model\IconizeInterface;
use Base\Routing\RouterInterface;
use Base\Service\Model\LinkableInterface;
use Base\Service\SettingBagInterface;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\ActionCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use ErrorException;
use Exception;
use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractCrudController extends \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController
{
    abstract public static function getPreferredIcon(): ?string;

    /**
     * @var AdminContextProvider
     * */
    protected AdminContextProvider $adminContextProvider;
    /**
     * @var AdminUrlGenerator
     * */
    protected AdminUrlGenerator $adminUrlGenerator;
    /**
     * @var ClassMetadataManipulator
     * */
    protected ClassMetadataManipulator $classMetadataManipulator;
    /**
     * @var EntityManagerInterface
     * */
    protected $entityManager;
    /**
     * @var RequestStack
     * */
    protected RequestStack $requestStack;
    /**
     * @var Extension
     * */
    protected Extension $extension;
    /**
     * @var SettingBagInterface
     * */
    protected SettingBagInterface $settingBag;
    /**
     * @var RouterInterface
     * */
    protected RouterInterface $router;
    /**
     * @var TranslatorInterface
     * */
    protected TranslatorInterface $translator;
    /**
     * @var FileService
     * */
    protected FileService $fileService;

    public function __construct(
        AdminContextProvider     $adminContextProvider,
        AdminUrlGenerator        $adminUrlGenerator,
        ClassMetadataManipulator $classMetadataManipulator,
        EntityManagerInterface   $entityManager,
        RequestStack             $requestStack,
        Extension                $extension,
        FileService              $fileService,
        SettingBagInterface      $settingBag,
        RouterInterface          $router,
        TranslatorInterface      $translator
    )
    {
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->entityManager = $entityManager;
        $this->adminContextProvider = $adminContextProvider;
        $this->requestStack = $requestStack;
        $this->extension = $extension;
        $this->translator = $translator;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->settingBag = $settingBag;
        $this->router = $router;
        $this->fileService = $fileService;

        $this->crud = null;
    }

    public function getTranslator()
    {
        return $this->translator;
    }

    public function getDiscriminatorMap(): array
    {
        return $this->classMetadataManipulator->getDiscriminatorMap(get_called_class()::getEntityFqcn());
    }

    public static array $crudNamespaceCandidates = ["\\\Controller\\\Crud\\", "\\\Controller\\\Backend\\\Crud\\"];

    public static function addCrudNamespaceCandidate(string $namespace)
    {
        if (in_array($namespace, self::$crudNamespaceCandidates)) {
            self::$crudNamespaceCandidates[] = $namespace;
        }
    }

    public static function removeCrudNamespaceCandidate(string $namespace)
    {
        array_remove(self::$crudNamespaceCandidates, $namespace);
    }

    public static function getEntityFqcn(): string
    {
        if (is_abstract(get_called_class())) {
            return false;
        }

        $entityFqcn = substr(get_called_class(), 0, -strlen("CrudController"));
        foreach (self::$crudNamespaceCandidates as $namespace) {
            try {
                $entityFqcn = preg_replace("/" . $namespace . "\/", "\\Entity\\", $entityFqcn);
                $aliasEntityFqcn = BaseBundle::getInstance()->getAlias($entityFqcn);
                if ($aliasEntityFqcn) {
                    $entityFqcn = $aliasEntityFqcn;
                }

                if (class_exists($entityFqcn)) {
                    self::$crudController[$entityFqcn] = get_called_class();
                    return $entityFqcn;
                }
            } catch (ErrorException $e) {
            }
        }

        throw new LogicException("Failed to find Entity FQCN from \"" . get_called_class() . "\" CRUD controller..\nDid you remove the Entity but kept the CRUD controller ?", 500);
    }

    protected static array $crudController = [];

    public static function getCrudControllerFqcn($entity, bool $inheritance = false): ?string
    {
        $entityFqcn = is_object($entity) ? get_class($entity) : ($entity !== null && class_exists($entity) ? $entity : null);
        if ($entityFqcn === null) {
            return null;
        }

        if (array_key_exists($entityFqcn, self::$crudController)) {
            return self::$crudController[$entityFqcn];
        }

        foreach (self::$crudNamespaceCandidates as $namespace) {
            $crudController = preg_replace('/\\\Entity\\\/', $namespace, $entityFqcn);
            $crudController = $crudController . "CrudController";

            if (false !== ($pos = strrpos($crudController, '\\__CG__\\'))) {
                $crudController = substr($crudController, $pos + 8);
            }

            $appCrudController = preg_replace("/^Base/", 'App', $crudController);
            $baseCrudController = preg_replace("/^App/", 'Base', $appCrudController);
            if (str_starts_with($crudController, "Base")) {
                if (class_exists($appCrudController) and !class_exists($crudController)) {
                    return $appCrudController;
                }
            }

            if (class_exists($appCrudController)) {
                return $appCrudController;
            }
            if (class_exists($baseCrudController)) {
                return $baseCrudController;
            }
        }

        return $inheritance ? self::getCrudControllerFqcn(get_parent_class($entity)) : null;
    }

    public static function getCrudTranslationPrefix()
    {
        return "@" . AbstractDashboardController::TRANSLATION_DASHBOARD . "." . self::getTranslationPrefix("Crud\\");
    }

    public static function getEntityTranslationPrefix()
    {
        return "@" . AbstractDashboardController::TRANSLATION_ENTITY . "." . self::getTranslationPrefix();
    }

    public static function getTranslationPrefix(?string $prefix = "")
    {
        $entityFqcn = preg_replace('/^(App|Base)\\\Entity\\\/', $prefix ?? "", get_called_class()::getEntityFqcn());
        return camel2snake(implode(".", array_unique(explode("\\", $entityFqcn))));
    }

    public function configureDiscriminatorMap(array $discriminatorMap, string $rootEntity, string $entity): ?array
    {
        return null;
    }

    protected static array $instantiationMap = [];

    public static function isInstantiable()
    {
        return static::$instantiationMap[self::class] ?? true;
    }

    public function allowInstantiation()
    {
        self::$instantiationMap[static::class] = true;
        return $this;
    }

    public function disallowInstantiation()
    {
        self::$instantiationMap[static::class] = false;
        return $this;
    }

    public function setDiscriminatorMapAttribute(Action $action)
    {
        $entity = $this->getEntityFqcn();
        $rootEntity = BaseBundle::getInstance()->getAlias($this->classMetadataManipulator->getRootEntityName($entity));
        $actionDto = $action->getAsDto();

        $discriminatorMap = $this->configureDiscriminatorMap($this->classMetadataManipulator->getDiscriminatorMap($entity), $rootEntity, $entity);
        if ($discriminatorMap === null) {
            $discriminatorMap = array_filter($this->classMetadataManipulator->getDiscriminatorMap($entity), fn($e) => is_instanceof($e, $entity));
        }

        $htmlAttributes = $actionDto->getHtmlAttributes();
        $htmlAttributes["crud"] = urlencode(get_class($this));
        $htmlAttributes["root-crud"] = urlencode($this->getCrudControllerFqcn($rootEntity));
        $htmlAttributes["map"] = [];

        foreach ($discriminatorMap as $key => $class) {
            $class = BaseBundle::getInstance()->getAlias($class);

            if (is_abstract($class)) {
                continue;
            }

            $k = explode("_", $key);
            $key = array_shift($k);

            if (($crudClassController = $this->getCrudControllerFqcn($class))) {
                $array = [implode("_", $k) => urlencode($crudClassController)];
                $htmlAttributes["map"][$key] = array_merge($htmlAttributes["map"][$key] ?? [], $array);
            }
        }

        if (count(array_filter($discriminatorMap, fn($e) => $e !== $entity)) > 0) {
            $actionDto->setHtmlElement("discriminator");
            $actionDto->setHtmlAttributes($htmlAttributes);
        }

        return $action;
    }

    public function configureActions(Actions $actions): Actions
    {
        $batchActionDelete = Action::new('batchActionDelete', '@' . AbstractDashboardController::TRANSLATION_DASHBOARD . '.action.batch_delete', 'fa-solid fa-user-times')
            ->linkToCrudAction('batchActionDelete')
            ->addCssClass('btn btn-primary text-danger');

        if (is_instanceof($this->getEntityFqcn(), LinkableInterface::class)) {
            $linkToEntity = \Base\Backend\Config\Action::new(\Base\Backend\Config\Action::GOTO, self::getEntityLabelInSingular(), "fa-solid fa-fw fa-plug")
                ->renderAsTooltip()
                ->linkToUrl(fn($e) => $e->__toLink() ?? "");

            $actions
                ->add(Action::INDEX, $linkToEntity);
        }

        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $a) => $this->setDiscriminatorMapAttribute($a))
            ->addBatchAction($batchActionDelete)
            ->setPermission($batchActionDelete, 'ROLE_SUPERADMIN')
            ->setPermission(Action::NEW, 'ROLE_SUPERADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPERADMIN');
    }

    public function batchActionDelete(BatchActionDto $batchActionDto)
    {
        foreach ($batchActionDto->getEntityIds() as $id) {
            $user = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);

            $this->entityManager->remove($user);
            $this->entityManager->flush($user);
        }

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    public function configureExtension(Extension $extension): Extension
    {
        return $extension;
    }

    public function getExtension(): Extension
    {
        return $this->extension;
    }

    protected ?Crud $crud = null;

    public function getCrud(): ?Crud
    {
        return $this->crud;
    }

    protected ?EntityDto $entityDto = null;

    public function getEntity()
    {
        return $this->entityDto?->getInstance();
    }

    public function getEntityDto(): EntityDto
    {
        return $this->entityDto;
    }

    public function getEntityCollection(): EntityCollection
    {
        return $this->entityCollection;
    }

    public static function getEntityLabelInSingular()
    {
        return self::getEntityTranslationPrefix() . "." . Translator::NOUN_SINGULAR;
    }

    public static function getEntityLabelInPlural()
    {
        return self::getEntityTranslationPrefix() . "." . Translator::NOUN_PLURAL;
    }

    public static function getEntityIcon()
    {
        $icon = get_called_class()::getPreferredIcon() ?? null;
        $entityFqcn = get_called_class()::getEntityFqcn();

        return $icon ?? (class_implements_interface($entityFqcn, IconizeInterface::class) ? $entityFqcn::__iconizeStatic()[0] : null);
    }

    public function configureCrud(Crud $crud): Crud
    {
        $this->crud = $crud;

        $entityLabelInSingular = $this->translator->transQuiet(self::getEntityLabelInSingular());
        $crud->getAsDto()->setEntityLabelInSingular($entityLabelInSingular ?? "");

        $entityLabelInPlural = $this->translator->transQuiet(self::getEntityLabelInPlural());
        $crud->getAsDto()->setEntityLabelInPlural($entityLabelInPlural ?? "");

        $crudTranslationPrefix = $this->getCrudTranslationPrefix();
        $action = $this->requestStack->getCurrentRequest()->query->get("crudAction") ?? "";
        $crudTranslationPrefixWithAction = $crudTranslationPrefix . ($action ? "." . $action : "");

        $title = $this->translator->transQuiet($crudTranslationPrefixWithAction . ".title");
        $title ??= $this->translator->transQuiet($crudTranslationPrefix . ".title");
        $title ??= $this->translator->transQuiet($crudTranslationPrefix . "." . Translator::NOUN_PLURAL);
        $title ??= $entityLabelInPlural ?? camel2snake(class_basename($this->getEntityFqcn()), " ");

        $help = $this->translator->transQuiet($crudTranslationPrefixWithAction . ".help");
        $help ??= $this->translator->transQuiet($crudTranslationPrefix . ".help");
        $help ??= "";

        $text = $this->translator->transQuiet($crudTranslationPrefixWithAction . ".text");
        $text ??= $this->translator->transQuiet($crudTranslationPrefix . ".text");
        $text ??= "";

        $this->extension->setIcon($this->getEntityIcon() ?? "fa-solid fa-question-circle");
        $this->extension->setTitle(mb_ucfirst($title));
        $this->extension->setHelp($help);
        $this->extension->setText($text);

        // Configure CRUD and extension
        return $this->configureExtension($this->extension)
            ->configureCrud($crud)
            ->showEntityActionsInlined()
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(10)
            ->setFormOptions(
                ['validation_groups' => ['new']],
                ['validation_groups' => ['edit']]
            );
    }

    public function configureEntityCollectionWithResponseParameters(?EntityCollection $entityCollection, KeyValueStore $responseParameters): ?EntityCollection
    {
        foreach ($entityCollection ?? [] as $entityDto) {
            $entityDto = $this->configureEntityDto($entityDto);

            $actions = $entityDto->getActions();
            foreach ($actions as $action) {
                $instance = $entityDto->getInstance();
                $crudController = $this->getCrudControllerFqcn($instance);

                $discriminatorValue = $this->classMetadataManipulator->getDiscriminatorValue($instance);
                if ($crudController && $discriminatorValue) {
                    $url = null;

                    $closure = $action->getUrl();
                    if ($closure instanceof Closure) {
                        $url = $action->getUrl()($instance);
                    }

                    $url = $url ?? $this->adminUrlGenerator
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
        if (!$entity) {
            return $extension;
        }

        $entityLabel = mb_ucfirst($this->translator->transEntity($entity));
        if ($entityLabel) {
            $extension->setTitle($entityLabel);
        }

        $entityLabel = mb_ucfirst($this->translator->transEntity(get_class($entity), null, Translator::NOUN_SINGULAR));
        if (is_stringeable($entity) && (string)$entity != get_class($entity)) {
            $entityLabel = (string)$entity;
        }

        $extension->setTitle(mb_ucfirst(is_stringeable($entity) && $entityLabel ? $entityLabel : $this->translator->transEntity(get_class($entity), null, Translator::NOUN_PLURAL)));

        if ($this->getCrud()->getAsDto()->getCurrentAction() != "new") {
            $entityLabel = mb_ucfirst($this->translator->transEntity(get_class($entity), null, Translator::NOUN_SINGULAR));
            $entityText = $entityLabel . " ID #" . $entity->getId();
            try { # Try to link without route parameter
                if ($entity instanceof LinkableInterface) {
                    $entityText = $entityLabel . " ID <a href='" . $entity->__toLink() . "'>#" . $entity->getId() . "</a>";
                }
            } catch (Exception $e) {
            }

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

    protected ?EntityCollection $entityCollection;

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $this->entityDto = $responseParameters->get("entity");

        $this->entityCollection = $responseParameters->get("entities");
        $this->entityCollection = $this->configureEntityCollectionWithResponseParameters(
            $this->entityCollection,
            $responseParameters
        );

        $responseParameters->set("entities", $this->entityCollection);
        if ($this->entityCollection) {
            $responseParameters->set("entities", $this->entityCollection);
        }

        $this->extension = $this->configureExtensionWithResponseParameters($this->extension, $responseParameters);
        return parent::configureResponseParameters($responseParameters);
    }

    protected bool $showId = true;

    public function showId()
    {
        $this->showId = true;
    }

    public function hideId()
    {
        $this->showId = false;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        array_prepend($args, fn() => yield ($this->showId ? IdField::new() : IdField::new()->hideOnIndex()));

        $yields = $this->yield($args);
        $simpleYields = array_filter_recursive(array_filter($yields, fn($k) => preg_match("/^[0-9.]+$/", $k), ARRAY_FILTER_USE_KEY));
        $associativeYields = array_diff_key($yields, $simpleYields);
        $simpleYields = array_values($simpleYields);

        $yields = [];
        foreach ($simpleYields ?? [] as $_) {
            foreach ($_ ?? [] as $yield) {
                $yields[] = $yield;
            }
        }

        $restYields = [];
        foreach ($associativeYields ?? [] as $path => $_) {
            foreach ($_ ?? [] as $i => $yield) {
                $property = preg_replace("/^[0-9.]+/", "", $path);

                $yieldList = array_map(fn($y) => $y->getAsDto()->getProperty(), $yields);
                $yieldPos = array_search($property, $yieldList);

                $yieldPosNext = next_key($yieldList, $yieldPos);
                $yieldPosNext = $yieldPosNext === false ? false : $yieldPosNext + $i;
                $yields = array_insert($yields, $yieldPosNext, $yield);
            }
        }

        foreach (array_flatten(".", array_concat($yields, $restYields), -1, ARRAY_FLATTEN_PRESERVE_KEYS) ?? [] as $yield) {
            yield $yield;
        }
    }

    public function yield(array &$args, ?string $field = null): array
    {
        $args = array_map_recursive(
            fn($v) => is_callable($v) ? $v() : $v,
            array_flatten(".", array_filter_recursive(
                $args,
                fn($v, $k) => ($field === null || $k == $field) && !empty($v),
                ARRAY_FILTER_USE_BOTH
            ), -1, ARRAY_FLATTEN_PRESERVE_KEYS)
        );

        $yields = [];
        foreach ($args as $field2 => $yield) {
            if ($field === null || $field == $field2) {
                $yields[$field2] = $yield;
            }
        }

        return $yields;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }

}
