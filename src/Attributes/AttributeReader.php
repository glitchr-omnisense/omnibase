<?php

namespace Base\Attributes;

use Doctrine\ORM\EntityManager;

use Base\Database\Entity\EntityHydrator;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\Persistence\ObjectRepository;
use Exception;

use App\Entity\User;
use Base\BaseBundle;
use Base\Cache\Abstract\AbstractLocalCache;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\FlysystemInterface;
use Base\Traits\SingletonTrait;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\User\UserInterface;

class AttributeReader extends AbstractLocalCache
{
    use SingletonTrait;

    public const TARGET_CLASS = "class";
    public const TARGET_METHOD = "method";
    public const TARGET_PROPERTY = "property";

    public const ALL_TARGETS = [
        self::TARGET_CLASS,
        self::TARGET_METHOD,
        self::TARGET_PROPERTY
    ];

    // Attribute pass..
    protected array $attributes = [];

    /**
     * @param $attributes
     * @return $this
     */
    public function addAttribute($attributes): self
    {
        $this->attributes[get_class($attributes)] = $attributes;
        return $this;
    }

    /**
     * @var EntityManager|null
     */
    protected ?EntityManager $entityManager = null;

    /**
     * @return EntityManager|null
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @param $entity
     * @return EntityRepository|ObjectRepository
     * @throws NotSupported
     */
    public function getRepository($entity)
    {
        return $this->entityManager->getRepository($entity);
    }

    public function isEntity(mixed $entity): bool
    {
        return $this->classMetadataManipulator->isEntity($entity);
    }

    /**
     * @var EntityHydrator|null
     */
    protected ?EntityHydrator $entityHydrator = null;

    public function getEntityHydrator(): EntityHydratorInterface
    {
        return $this->entityHydrator;
    }

    /**
     * @var ClassMetadataManipulator|null
     */
    protected ?ClassMetadataManipulator $classMetadataManipulator = null;

    public function getClassMetadataManipulator(): ClassMetadataManipulator
    {
        return $this->classMetadataManipulator;
    }

    /**
     * @var FlysystemInterface|null
     */
    protected ?FlysystemInterface $flysystem = null;

    public function getFlysystem(): FlysystemInterface
    {
        return $this->flysystem;
    }

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    public function getParameterBag(): ParameterBagInterface
    {
        return $this->parameterBag;
    }

    /**
     * @var string
     */
    protected string $environment;

    /**
     * @var string
     */
    protected string $projectDir;

    /**
     * @var string
     */
    protected string $cacheDir;

    /**
     * @var bool
     */
    protected bool $enabled;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * @var TokenStorageInterface
     */
    protected TokenStorageInterface $tokenStorage;

    /**
     * @var EventDispatcherInterface
     */
    protected EventDispatcherInterface $eventDispatcher;

    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        AdvancedRouterInterface          $router,
        EntityManager            $entityManager,
        ParameterBagInterface    $parameterBag,
        FlysystemInterface       $flysystem,
        RequestStack             $requestStack,
        TokenStorageInterface    $tokenStorage,
        EntityHydrator           $entityHydrator,
        ClassMetadataManipulator $classMetadataManipulator,
        string                   $projectDir,
        string                   $environment,
        string                   $cacheDir
    )
    {
        // The reader the CURRENT container just built always becomes the
        // singleton. This used to be "only if there is none yet", so in any
        // process that boots a second kernel (BaseBundle::boot() builds a
        // fresh reader every time) getInstance() kept returning the FIRST
        // kernel's reader, wired to a container that no longer exists -
        // attributes such as GenerateUuid then silently stopped applying
        // ("Column uuid cannot be null"). Same bug class as the memoised
        // statics BaseCommonTrait::setRuntime() now clears. The reader is a
        // shared service, so within one kernel this still runs exactly once.
        self::setInstance($this);

        // Check if custom reader is enabled
        $this->parameterBag = $parameterBag;
        $this->enabled = $parameterBag->get("base.attributes.use_custom") ?? false;

        $paths = [];
        $paths[] = __DIR__ . "/Attribute";
        $paths[] = __DIR__ . "/../Database/Attribute";
        if (($matches = preg_grep('/^base.attributes.paths\.[0-9]*\.[.*]*$/', array_keys($parameterBag->all())))) {
            foreach ($matches as $match) {
                $paths[] = $parameterBag->get($match);
            }
        }

        // Paths to look for attributes
        foreach ($paths as $path) {
            $this->addPath($path);
        }

        $this->entityManager = $entityManager;
        $this->entityHydrator = $entityHydrator;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
        $this->flysystem = $flysystem;
        $this->eventDispatcher = $eventDispatcher;
        $this->router = $router;
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->environment = $environment;
        $this->projectDir = $projectDir;

        parent::__construct($cacheDir);
    }

    /**
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @return string
     */
    public function getProjectDir()
    {
        return $this->projectDir;
    }

    protected $cache = null;
    protected array $cachePool = [];

    protected array $attributeTargets = [];

    protected array $classAncestors = [];
    protected array $classHierarchies = [];

    protected array $classAttributes = [];
    protected array $methodAttributes = [];
    protected array $propertyAttributes = [];

    /**
     * @var array
     */
    protected array $paths = [];

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function addPath(string $path): self
    {
        if (in_array($path, $this->paths)) {
            return $this;
        }

        if (!file_exists($path)) {
            return $this;
        }
        foreach (BaseBundle::getInstance()->getAllClasses($path) as $attribute) {
            $this->addAttributeName($attribute);
        }

        return $this;
    }

    public function getAsset(string $url): string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);
        if ($parseUrl["scheme"] ?? false) {
            return $url;
        }

        $request = $this->requestStack->getCurrentRequest();
        $baseDir = $request ? $request->getBasePath() : $_SERVER["CONTEXT_PREFIX"] ?? "";

        $path = trim($parseUrl["path"]);
        if ($path == "/") {
            return $baseDir;
        } elseif (!str_starts_with($path, "/")) {
            $path = $baseDir . "/" . $path;
        }

        return $path;
    }

    public function getUser(): ?UserInterface
    {
        return $this->tokenStorage->getToken()?->getUser();
    }

    public function getImpersonator(): ?User
    {
        $token = $this->tokenStorage->getToken();
        return ($token instanceof SwitchUserToken ? $token->getOriginalToken()->getUser() : null);
    }

    protected array $attributeNames = [];

    public function getAttributeNames(): array
    {
        return $this->attributeNames;
    }

    /**
     * @param string $attributeName
     * @return $this
     */
    public function addAttributeName(string $attributeName)
    {
        if (!is_subclass_of($attributeName, AbstractAttribute::class)) {
            return $this;
        }

        if (!in_array($attributeName, $this->attributeNames)) {
            $this->attributeNames[] = $attributeName;
        }

        return $this;
    }

    /**
     * @param string $attributeName
     * @return $this
     */
    public function removeAttributeName(string $attributeName)
    {
        if (($pos = array_search($attributeName, $this->attributeNames))) {
            unset($this->attributeNames[$pos]);
        }

        return $this;
    }

    public function getAttributeTargets(object|string $className): array // Attribute class
    {
        $className = (is_object($className) ? get_class($className) : $className);

        if (array_key_exists($className, $this->attributeTargets)) {
            return $this->attributeTargets[$className];
        }

        $reflClass = new ReflectionClass($className);

        $attributeTargets = [];
        $reflClass = new ReflectionClass($className);
        if(!empty($reflClass->getAttributes())) {

            foreach($reflClass->getAttributes() as $attribute) {
                
                if($attribute->getName() != "Attribute") continue;
                
                $targets = $attribute->getArguments()[0] ?? 0;
                if($targets & \Attribute::TARGET_CLASS) $attributeTargets[] = "class";
                if($targets & \Attribute::TARGET_METHOD) $attributeTargets[] = "method";
                if($targets & \Attribute::TARGET_PROPERTY) $attributeTargets[] = "property";
            }
            
        } else {

            if (preg_match_all('/@Target\(\{(.*)\}\)/', $reflClass->getDocComment(), $matches, PREG_SET_ORDER)) {
                $attributeTargets = json_decode(mb_strtolower("[" . end($matches)[1] . "]"));
            }
        }

        foreach ($attributeTargets as $target) {
            switch ($target) {
                case 'all':
                    $this->attributeTargets[$className][] = "class";
                    $this->attributeTargets[$className][] = "method";
                    $this->attributeTargets[$className][] = "property";

                    // Not used at this time by the custom AttributeReader
                    $this->attributeTargets[$className][] = "attribute";
                    $this->attributeTargets[$className][] = "attribute";
                    $this->attributeTargets[$className][] = "function";
                    break;

                case 'class':
                case 'method':
                case 'property':
                case 'attribute':
                case 'function':
                    $this->attributeTargets[$className][] = $target;
                    break;

                default:
                    throw new Exception("Unexpected @Target parameter in " . $className);
            }
        }

        $this->setCache("/Targets", $this->attributeTargets, null, true);
        return $this->attributeTargets[$className] ?? [];
    }

    /**
     * Everything related to hierarchy
     *
     * @return string|null
     * @var string
     */
    public function getParent(string $className): ?string
    {
        return $this->classHierarchies[$className] ?? null;
    }

    /**
     * @param $className
     * @return string|null
     */
    public function getAncestor($className): ?string
    {
        if (!array_key_exists($className, $this->classAncestors)) {
            $classAncestor = $className;
            while (($parentClass = $this->getParent($classAncestor))) {
                $classAncestor = $parentClass;
            }

            $this->classAncestors[$className] = $classAncestor;
            $this->setCache("/Ancestors", $this->classAncestors, null, true);
        }

        return $this->classAncestors[$className];
    }

    /**
     * @param $className
     * @param $attributeNames
     * @param $attributeTargets
     * @return array[]
     * @throws Exception
     */
    public function getAncestorAttributes($className, $attributeNames = null, $attributeTargets = [])
    {
        $classAncestor = $this->getAncestor($className);
        return $this->getAttributes($classAncestor, $attributeNames, $attributeTargets);
    }

    /**
     * @param $className
     * @param $attributeNames
     * @param $attributeTargets
     * @return array[]
     * @throws Exception
     */
    public function getParentAttributes($className, $attributeNames = null, $attributeTargets = [])
    {
        $parent = $this->getParent($className);
        return $this->getAttributes($parent, $attributeNames, $attributeTargets);
    }

    /**
     * @param $className
     * @return array
     */
    public function getChildren($className)
    {
        $children = [];

        foreach (get_declared_classes() as $candidate) {
            if (is_subclass_of($candidate, $className)) {
                $children[] = $candidate;
            }
        }

        return $children;
    }

    /**
     * @param $className
     * @param $attributeNames
     * @param $attributeTargets
     * @return array|mixed
     * @throws Exception
     */
    public function getChildrenAttributes($className, $attributeNames = null, $attributeTargets = [])
    {
        $attributes = [];
        foreach ($this->getChildren($className) as $child) {
            $childrenAttributes = $this->getChildrenAttributes($child, $attributeNames, $attributeTargets);
            $attributes = array_append_recursive(
                $attributes,
                array_append_recursive(
                    $this->getAttributes($child, $attributeNames, $attributeTargets),
                    $childrenAttributes
                )
            );
        }

        return $attributes;
    }

    public function getClassAttributes(mixed $classNameOrMetadataOrRefl, mixed $attributeNames = null, array $attributeTargets = []): array
    {
        $attributeNames = $this->normalizeNames($attributeNames);
        $attributeTargets = $this->normalizeTargets($attributeTargets, $attributeNames);
        if (!in_array(self::TARGET_CLASS, $attributeTargets)) {
            return [];
        }

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        if (!array_key_exists($reflClass->name, $this->classAttributes)) {

            $this->classAttributes[$reflClass->name] = [];
            
            foreach($reflClass->getAttributes() as $attribute) {

                $attribute = $attribute->newInstance();
                if (!is_serializable($attribute)) {
                    throw new Exception("Attribute \"" . get_class($attribute) . "\" failed to serialize. Please implement __serialize/__unserialize, or double-check properties.");
                }

                $this->classAttributes[$reflClass->name][] = $attribute;
            }

            $this->setCache("/ClassAttributes", $this->classAttributes, null, true);
        }

        return $this->filterClassAttributes($reflClass->name, $attributeNames);
    }

    /**
     * @param string $className
     * @param mixed $attributeNames
     * @param array|null $classAttributes
     * @return array|mixed
     */
    protected function filterClassAttributes(string $className, mixed $attributeNames, ?array $classAttributes = null)
    {
        $classAttributes ??= $this->classAttributes;

        // Return the full set of attributes for a given class
        if ($attributeNames == $this->getAttributeNames()) {
            return $classAttributes[$className] ?? [];
        }

        // Filter them ask request by the $annontationNames
        $filteredAttributes = [];
        foreach ($classAttributes[$className] ?? [] as $attribute) {
            if (in_array(get_class($attribute), $attributeNames)) {
                $filteredAttributes[] = $attribute;
            }
        }

        return $filteredAttributes;
    }

    public function getMethodAttributes(mixed $classNameOrMetadataOrRefl, mixed $attributeNames = null, array $attributeTargets = []): array
    {
        $attributeNames = $this->normalizeNames($attributeNames);
        $attributeTargets = $this->normalizeTargets($attributeTargets, $attributeNames);
        if (!in_array(self::TARGET_METHOD, $attributeTargets)) {
            return [];
        }

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        if (!array_key_exists($reflClass->name, $this->methodAttributes)) {

            // Compute the class attributes
            $this->methodAttributes[$reflClass->name] = [];
            foreach ($reflClass->getMethods() as $reflMethod) {

                $this->methodAttributes[$reflClass->name][$reflMethod->name] = [];
                foreach($reflMethod->getAttributes() as $attribute) {

                    $attribute = $attribute->newInstance();
                    if (!is_serializable($attribute)) {
                        throw new Exception("Attribute \"" . get_class($attribute) . "\" failed to serialize. Please implement __serialize/__unserialize, or double-check properties.");
                    }

                    $this->methodAttributes[$reflClass->name][$reflMethod->name][] = $attribute;
                }
            }

            $this->setCache("/MethodAttributes", $this->methodAttributes, null, true);
        }

        return $this->filterMethodAttributes($reflClass->name, $attributeNames);
    }

    /**
     * @param string $className
     * @param mixed $attributeNames
     * @param array|null $methodAttributes
     * @return array|mixed
     */
    protected function filterMethodAttributes(string $className, mixed $attributeNames, ?array $methodAttributes = null)
    {
        $methodAttributes ??= $this->methodAttributes;

        // Return the full set of attributes for a given class
        if ($attributeNames == $this->getAttributeNames()) {
            return $methodAttributes[$className] ?? [];
        }

        // Filter them ask request by the $annontationNames
        $filteredAttributes = [];
        foreach ($methodAttributes[$className] ?? [] as $method => $_) {
            foreach ($_ as $attribute) {
                if (in_array(get_class($attribute), $attributeNames)) {
                    $filteredAttributes[$method][] = $attribute;
                }
            }
        }

        return $filteredAttributes;
    }

    public function getPropertyAttributes(mixed $classNameOrMetadataOrRefl, mixed $attributeNames = null, array $attributeTargets = []): array
    {
        $attributeNames = $this->normalizeNames($attributeNames);
        $attributeTargets = $this->normalizeTargets($attributeTargets, $attributeNames);
        if (!in_array(self::TARGET_PROPERTY, $attributeTargets)) {
            return [];
        }

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        if (!array_key_exists($reflClass->name, $this->propertyAttributes)) {

            // Force to get all known attributes when buffering
            $this->propertyAttributes[$reflClass->name] = [];
            foreach ($reflClass->getProperties() as $reflProperty) {

                $this->propertyAttributes[$reflClass->name][$reflProperty->name] = [];
                foreach($reflProperty->getAttributes() as $attribute) {

                    $attribute = $attribute->newInstance();
                    if (!is_serializable($attribute)) {
                        throw new Exception("Attribute \"" . get_class($attribute) . "\" failed to serialize. Please implement __serialize/__unserialize, or double-check properties.");
                    }

                    $this->propertyAttributes[$reflClass->name][$reflProperty->name][] = $attribute;
                }
            }

            $this->setCache("/PropertyAttributes", $this->propertyAttributes, null, true);
        }

        return $this->filterPropertyAttributes($reflClass->name, $attributeNames);
    }

    /**
     * @param string $className
     * @param mixed $attributeNames
     * @param array|null $propertyAttributes
     * @return array|mixed
     */
    protected function filterPropertyAttributes(string $className, mixed $attributeNames, ?array $propertyAttributes = null)
    {
        $propertyAttributes ??= $this->propertyAttributes;

        // Return the full set of attributes for a given class
        if ($attributeNames == $this->getAttributeNames()) {
            return $propertyAttributes[$className] ?? [];
        }

        // Filter them by the $annontationNames
        $filteredAttributes = [];
        foreach ($propertyAttributes[$className] ?? [] as $property => $_) {
            foreach ($_ as $attribute) {
                if (in_array(get_class($attribute), $attributeNames)) {
                    $filteredAttributes[$property][] = $attribute;
                }
            }
        }

        return $filteredAttributes;
    }

    /**
     * @param $classNameOrMetadataOrRefl
     * @return ReflectionClass|null
     */
    public function getReflClass($classNameOrMetadataOrRefl)
    {
        if ($classNameOrMetadataOrRefl instanceof ReflectionClass) {
            return $classNameOrMetadataOrRefl;
        } elseif ($classNameOrMetadataOrRefl instanceof ClassMetadata) {
            return $classNameOrMetadataOrRefl->getReflectionClass();
        } else {
            return new ReflectionClass($classNameOrMetadataOrRefl);
        }
    }

    public function normalizeNames(mixed $attributeNames, bool $fallbackAttributeNames = true): ?array
    {
        $attributeNames = $attributeNames === null ? null : array_unique(
            (is_array($attributeNames) ? $attributeNames :
                (is_object($attributeNames) ? [get_class($attributeNames)] :
                    (is_string($attributeNames) ? [$attributeNames] : [])))
        );

        if ($fallbackAttributeNames && empty($attributeNames)) {
            $attributeNames = $this->getAttributeNames();
        }

        return $attributeNames;
    }

    /**
     * @param $attributeTargets
     * @param $attributeNames
     * @return mixed|string[]
     * @throws Exception
     */
    public function normalizeTargets($attributeTargets, $attributeNames)
    {
        if (empty($attributeTargets)) {
            foreach ($attributeNames as $attributeName) {
                $attributeTargets = array_merge($attributeTargets, $this->getAttributeTargets($attributeName));
            }

            $attributeTargets = array_unique($attributeTargets);
            if (empty($attributeTargets)) {
                $attributeTargets = self::ALL_TARGETS;
            }
        }

        asort($attributeTargets);
        return $attributeTargets;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        // Cheap cache-bucket loads ONLY — this runs in the CONSTRUCTOR
        // (SimpleCacheTrait::__construct), which Doctrine may invoke while
        // lazily instantiating event listeners MID-dispatch. Anything heavier
        // here (in particular anything loading entity metadata) re-enters the
        // half-initialized event manager: the historical ClassMetadata race.
        // The heavy precompute lives in precompute(), driven by
        // AttributeCacheWarmer at cache:warmup time; classes not covered by
        // a warm cache still resolve lazily in getAttributes().
        $this->attributeTargets = $this->getCache("/Targets") ?? [];
        $this->classHierarchies = $this->getCache("/Hierarchies") ?? [];
        $this->classAncestors = $this->getCache("/Ancestors") ?? [];
        $this->classAttributes = $this->getCache("/ClassAttributes") ?? [];
        $this->methodAttributes = $this->getCache("/MethodAttributes") ?? [];
        $this->propertyAttributes = $this->getCache("/PropertyAttributes") ?? [];

        return [];
    }

    /**
     * Precompute attributes for every entity class and every routed
     * controller. Iterating the ROUTE COLLECTION makes ApiPlatform load
     * entity metadata, so this must NEVER run from a runtime constructor —
     * only from the cache warmer (cache:clear / cache:warmup), where no
     * Doctrine event dispatch is in flight. Idempotent per cache lifetime
     * via executeOnce.
     */
    public function precompute(): void
    {
        $this->executeOnce(function () {

            foreach ($this->classMetadataManipulator->getAllClassNames() as $className) {
                $this->getAncestor($className);
                $this->getAttributes($className);
            }

            // Warmup controllers
            foreach ($this->router->getRouteCollection()->all() as $route) {
                $controller = $route->getDefaults()["_controller"] ?? "";
                // routes may declare [Class::class, 'method'] instead of "Class::method"
                if (is_array($controller)) {
                    $controller = implode("::", $controller);
                }
                $className = is_string($controller) ? (explode("::", $controller)[0] ?? "") : "";
                if (!$className || !class_exists($className)) {
                    continue;
                }

                $this->getAncestor($className);
                $this->getAttributes($className);
            }

            $this->commitCache();
        });
    }

    /**
     * @param $classNameOrMetadataOrRefl
     * @param $attributeNames
     * @param array $attributeTargets
     * @return array|array[]
     * @throws Exception
     */
    public function getAttributes($classNameOrMetadataOrRefl, $attributeNames = null, array $attributeTargets = []): array
    {
        // Termination
        if ($classNameOrMetadataOrRefl == null) {
            return [];
        }

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        $attributes = [self::TARGET_CLASS => [], self::TARGET_METHOD => [], self::TARGET_PROPERTY => []];
        $attributeNames = $this->normalizeNames($attributeNames);
        $attributeTargets = $this->normalizeTargets($attributeTargets, $attributeNames);

        //
        // Class not yet visited.. determine parent class
        if (!array_key_exists($reflClass->getName(), $this->classHierarchies)) {
            $this->classHierarchies[$reflClass->getName()] = null;
            if (($parentClassName = get_parent_class($reflClass->getName()))) {
                $this->classHierarchies[$reflClass->getName()] = $parentClassName;
            }

            $this->setCache("/Hierarchies", $this->classHierarchies, null, true);
        }


        // Get class attributes
        if (in_array(self::TARGET_CLASS, $attributeTargets)) {
            $attributes[self::TARGET_CLASS][$reflClass->getName()] =
                $this->getClassAttributes($reflClass, $attributeNames, $attributeTargets);
        }

        // Get method attributes
        if (in_array(self::TARGET_METHOD, $attributeTargets)) {
            $attributes[self::TARGET_METHOD][$reflClass->getName()] =
                $this->getMethodAttributes($reflClass, $attributeNames, $attributeTargets);
        }

        // Get properties attributes
        if (in_array(self::TARGET_PROPERTY, $attributeTargets)) {
            $attributes[self::TARGET_PROPERTY][$reflClass->getName()] =
                $this->getPropertyAttributes($reflClass, $attributeNames, $attributeTargets);
        }

        return $attributes;
    }
}
