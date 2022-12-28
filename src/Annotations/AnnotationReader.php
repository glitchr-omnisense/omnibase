<?php

namespace Base\Annotations;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineAnnotationReader;
use Doctrine\ORM\EntityManager;

use Base\Annotations\AbstractAnnotation;
use Base\Database\Entity\EntityHydrator;
use Exception;

use App\Entity\User;
use Base\BaseBundle;
use Base\Cache\SimpleCache;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\Mapping\ClassMetadataManipulator;

use Base\Service\FlysystemInterface;
use Base\Traits\SingletonTrait;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

class AnnotationReader extends SimpleCache
{
    use SingletonTrait;

    public const TARGET_CLASS    = "class";
    public const TARGET_METHOD   = "method";
    public const TARGET_PROPERTY = "property";

    public const ALL_TARGETS = [
        self::TARGET_CLASS,
        self::TARGET_METHOD,
        self::TARGET_PROPERTY
    ];

    /**
     * @var EntityManager
     */
    protected $entityManager = null;
    public function getEntityManager() { return $this->entityManager; }
    public function getRepository($entity) { return $this->entityManager->getRepository($entity); }
    public function isEntity(mixed $entity)         : bool            { return $this->classMetadataManipulator->isEntity($entity); }

    /**
     * @var EntityHydrator
     */
    protected $entityHydrator = null;
    public function getEntityHydrator(): EntityHydratorInterface { return $this->entityHydrator; }

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;
    public function getClassMetadataManipulator(): ClassMetadataManipulator { return $this->classMetadataManipulator; }

    /**
     * @var FlysystemInterface
     */
    protected $flysystem = null;
    public function getFlysystem(): FlysystemInterface { return $this->flysystem; }

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;
    public function getParameterBag(): ParameterBagInterface { return $this->parameterBag; }

    /**
     * @var DoctrineAnnotationReader
     */
    protected $reader = null;
    public function getDefaultReader(): DoctrineAnnotationReader { return $this->reader; }

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
    protected $requestStack;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EntityManager $entityManager,
        ParameterBagInterface $parameterBag,
        FlysystemInterface $flysystem,
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage,
        EntityHydrator $entityHydrator,
        ClassMetadataManipulator $classMetadataManipulator,
        string $projectDir, string $environment, string $cacheDir)
    {

        if(!self::getInstance(false))
            self::setInstance($this);

        $this->reader = new DoctrineAnnotationReader();

        // Check if custom reader is enabled
        $this->parameterBag = $parameterBag;
        $this->enabled = $parameterBag->get("base.annotations.use_custom_reader");

        $paths   = [];
        $paths[] = __DIR__ . "/Annotation";
        $paths[] = __DIR__ . "/../Database/Annotation";
        if ( ($matches = preg_grep('/^base.annotations.paths\.[0-9]*\.[.*]*$/', array_keys($parameterBag->all()))) )
            foreach ($matches as $match) $paths[] = $parameterBag->get($match);

        // Paths to look for annotations
        foreach ($paths as $path)
            $this->addPath($path);

        $this->entityManager   = $entityManager;
        $this->entityHydrator  = $entityHydrator;
        $this->requestStack    = $requestStack;
        $this->tokenStorage    = $tokenStorage;
        $this->flysystem       = $flysystem;
        $this->eventDispatcher = $eventDispatcher;
        $this->classMetadataManipulator   = $classMetadataManipulator;

        $this->environment = $environment;
        $this->projectDir  = $projectDir;

        parent::__construct($cacheDir);
    }

    public function getEnvironment() { return $this->environment; }
    public function getProjectDir() { return $this->projectDir; }

    protected $cache = null;
    protected $cachePool = [];

    protected array $annotationTargets = [];

    protected array $classAncestors    = [];
    protected array $classHierarchies  = [];

    protected array $familyAnnotations   = [];
    protected array $classAnnotations    = [];
    protected array $methodAnnotations   = [];
    protected array $propertyAnnotations = [];

    /**
     * @var array
     */
    protected array $paths = [];
    public function getPaths(): array { return $this->paths; }
    public function addPath(string $path): self
    {
        if (in_array($path, $this->paths)) return $this;

        if (!file_exists($path)) return $this;
            //throw new Exception("Path not found: \"".$path."\"");

        foreach (BaseBundle::getAllClasses($path) as $annotation)
            $this->addAnnotationName($annotation);

        return $this;
    }

    public function getAsset(string $url): string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);
        if($parseUrl["scheme"] ?? false)
            return $url;

        $request = $this->requestStack->getCurrentRequest();
        $baseDir = $request ? $request->getBasePath() : $_SERVER["CONTEXT_PREFIX"] ?? "";

        $path = trim($parseUrl["path"]);
        if($path == "/") return $baseDir;
        else if(!str_starts_with($path, "/"))
            $path = $baseDir."/".$path;

        return $path;
    }

    public function getUser(): ?User { return $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null; }
    public function getImpersonator(): ?User
    {
        $token = $this->tokenStorage->getToken();
        return ($token instanceof SwitchUserToken ? $token->getOriginalToken()->getUser() : null);
    }

    protected array $annotationNames = [];
    public function getAnnotationNames(): array { return $this->annotationNames; }
    public function addAnnotationName(string $annotationName)
    {
        if (!is_subclass_of($annotationName, AbstractAnnotation::class))
            return $this;

        if (!in_array($annotationName, $this->annotationNames))
            $this->annotationNames[] = $annotationName;

        return $this;
    }

    public function removeAnnotationName(string $annotationName)
    {
        if (($pos = array_search($annotationName, $this->annotationNames)))
            unset($this->annotationNames[$pos]);

        return $this;
    }

    public function getAnnotationTargets(object|string $className): array // Annotation class
    {
        $className = (is_object($className) ? get_class($className) : $className);

        if (array_key_exists($className, $this->annotationTargets)) {
            return $this->annotationTargets[$className];
        }

        $reflClass = new \ReflectionClass($className);

        $annotationTargets = [];
        if (preg_match_all('/@Target\(\{(.*)\}\)/', $reflClass->getDocComment(), $matches, PREG_SET_ORDER))
            $annotationTargets = json_decode(mb_strtolower("[" . end($matches)[1] . "]"));

        foreach ($annotationTargets as $target) {

            switch ($target) {

                case 'all':
                    $this->annotationTargets[$className][] = "class";
                    $this->annotationTargets[$className][] = "method";
                    $this->annotationTargets[$className][] = "property";

                    // Not used at this time by the custom AnnotationReader
                    $this->annotationTargets[$className][] = "annotation";
                    $this->annotationTargets[$className][] = "function";
                    break;

                case 'class':
                case 'method':
                case 'property':
                case 'annotation':
                case 'function':
                    $this->annotationTargets[$className][] = $target;
                    break;

                default:
                    throw new Exception("Unexpected @Target parameter in " . $className);
            }
        }

        $this->setCache("/Targets", $this->annotationTargets, true);
        return $this->annotationTargets[$className] ?? [];
    }

    /**
     * Everything related to hierarchy
     *
     * @var array
     */
    public function getParent($className): ?string { return $this->classHierarchies[$className] ?? null; }
    public function getAncestor($className): ?string
    {
        if (!array_key_exists($className, $this->classAncestors)) {

            $classAncestor = $className;
            while ( ($parentClass = $this->getParent($classAncestor)) )
                $classAncestor = $parentClass;

            $this->classAncestors[$className] = $classAncestor;
            $this->setCache("/Ancestors", $this->classAncestors, true);
        }

        return $this->classAncestors[$className];
    }

    public function getAncestorAnnotations($className, $annotationNames = null, $annotationTargets = [])
    {
        $classAncestor = $this->getAncestor($className);
        return $this->getAnnotations($classAncestor, $annotationNames, $annotationTargets);
    }

    public function getParentAnnotations($className, $annotationNames = null, $annotationTargets = [])
    {
        $parent = $this->getParent($className);
        return $this->getAnnotations($parent, $annotationNames, $annotationTargets);
    }

    public function getChildren($className)
    {
        $children = [];

        foreach (get_declared_classes() as $candidate) {

            if (is_subclass_of($candidate, $className))
                $children[] = $candidate;
        }

        return $children;
    }

    public function getChildrenAnnotations($className, $annotationNames = null, $annotationTargets = [])
    {
        $annotations = [];
        foreach ($this->getChildren($className) as $child) {

            $childrenAnnotations = $this->getChildrenAnnotations($child, $annotationNames, $annotationTargets);
            $annotations = array_append_recursive(
                $annotations,
                array_append_recursive(
                    $this->getAnnotations($child, $annotationNames, $annotationTargets),
                    $childrenAnnotations
                )
            );
        }

        return $annotations;
    }

    public function getDefaultClassAnnotations($classNameOrMetadataOrRefl, $annotationNames = null)
    {
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);

        $annotations = $this->getDefaultReader()->getClassAnnotations($reflClass);
        $annotationNames = $this->normalizeNames($annotationNames, false);

        return array_filter($annotations, fn($a) => $annotationNames === null || in_array(get_class($a), $annotationNames));
    }

    public function getClassAnnotations(mixed $classNameOrMetadataOrRefl, mixed $annotationNames = null, array $annotationTargets = []): array
    {
        $annotationNames = $this->normalizeNames($annotationNames);
        $annotationTargets = $this->normalizeTargets($annotationTargets, $annotationNames);
        if (!in_array(self::TARGET_CLASS, $annotationTargets))
            return [];

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        if (!array_key_exists($reflClass->name, $this->classAnnotations)) {

            // Compute the class annotations
            $this->classAnnotations[$reflClass->name] = [];
            foreach ($this->getDefaultReader()->getClassAnnotations($reflClass) as $annotation) {

                if (!is_serializable($annotation))
                    throw new Exception("Annotation \"".get_class($annotation)."\" failed to serialize. Please implement __serialize/__unserialize, or double-check properties.");

                $this->classAnnotations[$reflClass->name][] = $annotation;
            }

            $this->setCache("/ClassAnnotations", $this->classAnnotations, true);
        }

        return $this->filterClassAnnotations($reflClass->name, $annotationNames);
    }

    protected function filterClassAnnotations(string $className, mixed $annotationNames, ?array $classAnnotations = null)
    {
        $classAnnotations ??= $this->classAnnotations;

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getAnnotationNames())
            return $classAnnotations[$className] ?? [];

        // Filter them ask request by the $annontationNames
        $filteredAnnotations = [];
        foreach($classAnnotations[$className] ?? [] as $annotation) {

            if( in_array(get_class($annotation), $annotationNames) )
                $filteredAnnotations[] = $annotation;
        }

        return $filteredAnnotations;
    }

    public function getDefaultMethodAnnotations(mixed $classNameOrMetadataOrRefl, mixed $annotationNames = null)
    {
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);

        $annotations = [];
        $annotationNames = $this->normalizeNames($annotationNames, false);

        foreach ($reflClass->getMethods() as $reflMethod) {

            $annotations[$reflMethod->getName()] = array_filter(
                $this->getDefaultReader()->getMethodAnnotations($reflMethod),
                fn($a) => $annotationNames === null || in_array(get_class($a), $annotationNames)
            );
        }

        return array_filter($annotations);
    }

    public function getMethodAnnotations(mixed $classNameOrMetadataOrRefl, mixed $annotationNames = null, array $annotationTargets = []): array
    {
        $annotationNames = $this->normalizeNames($annotationNames);
        $annotationTargets = $this->normalizeTargets($annotationTargets, $annotationNames);
        if (!in_array(self::TARGET_METHOD, $annotationTargets))
            return [];

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        if (!array_key_exists($reflClass->name, $this->methodAnnotations)) {

            // Compute the class annotations
            $this->methodAnnotations[$reflClass->name] = [];
            foreach ($reflClass->getMethods() as $reflMethod) {

                $this->methodAnnotations[$reflClass->name][$reflMethod->name] = [];
                foreach ($this->getDefaultReader()->getMethodAnnotations($reflMethod) as $annotation) {

                    if (!is_serializable($annotation))
                        throw new Exception("Annotation \"".get_class($annotation)."\" failed to serialize. Please implement __serialize/__unserialize, or double-check properties.");

                    $this->methodAnnotations[$reflClass->name][$reflMethod->name][] = $annotation;
                }
            }

            $this->setCache("/MethodAnnotations", $this->methodAnnotations, true);
        }

        return $this->filterMethodAnnotations($reflClass->name, $annotationNames);
    }

    protected function filterMethodAnnotations(string $className, mixed $annotationNames, ?array $methodAnnotations = null)
    {
        $methodAnnotations ??= $this->methodAnnotations;

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getAnnotationNames())
            return $methodAnnotations[$className] ?? [];

        // Filter them ask request by the $annontationNames
        $filteredAnnotations = [];
        foreach($methodAnnotations[$className] ?? [] as $method => $_) {

            foreach($_ as $annotation) {

                if( in_array(get_class($annotation), $annotationNames) )
                    $filteredAnnotations[$method][] = $annotation;
            }
        }

        return $filteredAnnotations;
    }

    public function getDefaultPropertyAnnotations(mixed $classNameOrMetadataOrRefl, mixed $annotationNames = null)
    {
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);

        $annotations = [];
        $annotationNames = $this->normalizeNames($annotationNames, false);

        foreach ($reflClass->getProperties() as $reflProperty) {

            $annotations[$reflProperty->getName()] = array_filter(
                $this->getDefaultReader()->getPropertyAnnotations($reflProperty),
                fn($a) => $annotationNames === null || in_array(get_class($a), $annotationNames)
            );
        }

        return array_filter($annotations);
    }

    public function getPropertyAnnotations(mixed $classNameOrMetadataOrRefl, mixed $annotationNames = null, array $annotationTargets = []): array
    {
        $annotationNames = $this->normalizeNames($annotationNames);
        $annotationTargets = $this->normalizeTargets($annotationTargets, $annotationNames);
        if (!in_array(self::TARGET_PROPERTY, $annotationTargets))
            return [];

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        if (!array_key_exists($reflClass->name, $this->propertyAnnotations)) {

            // Force to get all known annotations when buffering
            $this->propertyAnnotations[$reflClass->name] = [];
            foreach ($reflClass->getProperties() as $reflProperty) {

                $this->propertyAnnotations[$reflClass->name][$reflProperty->name] = [];
                foreach ($this->getDefaultReader()->getPropertyAnnotations($reflProperty) as $annotation) {

                    if (!is_serializable($annotation))
                        throw new Exception("Annotation \"".get_class($annotation)."\" failed to serialize. Please implement __serialize/__unserialize, or double-check properties.");

                    $this->propertyAnnotations[$reflClass->name][$reflProperty->name][] = $annotation;
                }
            }

            $this->setCache("/PropertyAnnotations", $this->propertyAnnotations, true);
        }

        return $this->filterPropertyAnnotations($reflClass->name, $annotationNames);
    }

    protected function filterPropertyAnnotations(string $className, mixed $annotationNames, ?array $propertyAnnotations = null)
    {
        $propertyAnnotations ??= $this->propertyAnnotations;

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getAnnotationNames())
            return $propertyAnnotations[$className] ?? [];

        // Filter them by the $annontationNames
        $filteredAnnotations = [];
        foreach($propertyAnnotations[$className] ?? [] as $property => $_) {

            foreach($_ as $annotation) {

                if( in_array(get_class($annotation), $annotationNames) )
                    $filteredAnnotations[$property][] = $annotation;
            }
        }

        return $filteredAnnotations;
    }

    public function getReflClass($classNameOrMetadataOrRefl)
    {
        if ($classNameOrMetadataOrRefl instanceof ReflectionClass)
            return $classNameOrMetadataOrRefl;
        else if ($classNameOrMetadataOrRefl instanceof ClassMetadata)
            return $classNameOrMetadataOrRefl->getReflectionClass();
        else
            return new \ReflectionClass($classNameOrMetadataOrRefl);
    }

    public function getDefaultAnnotations($classNameOrMetadataOrRefl, $annotationNames = null, array $annotationTargets = []): array
    {
        if ($classNameOrMetadataOrRefl == null) return [];
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);

        $annotations = [self::TARGET_CLASS => [],self::TARGET_METHOD => [],self::TARGET_PROPERTY => []];
        $annotationNames = $this->normalizeNames($annotationNames, false);
        $annotationTargets = $this->normalizeTargets($annotationTargets, $annotationNames);

        // Get class annotations
        if (in_array(self::TARGET_CLASS, $annotationTargets)) {

            $annotations[self::TARGET_CLASS][$reflClass->getName()] =
                $this->getDefaultClassAnnotations($reflClass, $annotationNames, $annotationTargets);
        }

        // Get method annotations
        if (in_array(self::TARGET_METHOD, $annotationTargets)) {

            $annotations[self::TARGET_METHOD][$reflClass->getName()] =
                $this->getDefaultMethodAnnotations($reflClass, $annotationNames, $annotationTargets);
        }

        // Get properties annotations
        if (in_array(self::TARGET_PROPERTY, $annotationTargets)) {

            $annotations[self::TARGET_PROPERTY][$reflClass->getName()] =
                $this->getDefaultPropertyAnnotations($reflClass, $annotationNames, $annotationTargets);
        }

        return $annotations;
    }

    public function normalizeNames(mixed $annotationNames, bool $fallbackAnnotationNames = true): ?array
    {
        $annotationNames = $annotationNames === null ? null : array_unique(
            (is_array($annotationNames) ? $annotationNames :
            (is_object($annotationNames) ? [get_class($annotationNames)] :
            (is_string($annotationNames) ? [$annotationNames] : [])))
        );

        if ($fallbackAnnotationNames && empty($annotationNames))
            $annotationNames = $this->getAnnotationNames();

        return $annotationNames;
    }

    public function normalizeTargets($annotationTargets, $annotationNames)
    {
        if (empty($annotationTargets)) {

            foreach ($annotationNames as $annotationName)
                $annotationTargets = array_merge($annotationTargets, $this->getAnnotationTargets($annotationName));

            $annotationTargets = array_unique($annotationTargets);
            if (empty($annotationTargets)) $annotationTargets = self::ALL_TARGETS;
        }

        asort($annotationTargets);
        return $annotationTargets;
    }

    public function warmUp(string $cacheDir): bool
    {
        $this->annotationTargets   = $this->getCache("/Targets") ?? [];
        $this->classHierarchies    = $this->getCache("/Hierarchies") ?? [];
        $this->classAncestors      = $this->getCache("/Ancestors") ?? [];
        $this->classAnnotations    = $this->getCache("/ClassAnnotations") ?? [];
        $this->methodAnnotations   = $this->getCache("/MethodAnnotations") ?? [];
        $this->propertyAnnotations = $this->getCache("/PropertyAnnotations") ?? [];

        /**
         * @var ClassMetadataFactory
         */
        $classMetadataFactory = $this->getEntityManager()->getMetadataFactory();
        foreach($classMetadataFactory->getAllClassNames() as $className) {

            $this->getAncestor($className);
            $this->getAnnotations($className);
        }

        $this->commitCache();
        return true;
    }

    public function getAnnotations($classNameOrMetadataOrRefl, $annotationNames = null, array $annotationTargets = []): array
    {
        // Termination
        if ($classNameOrMetadataOrRefl == null) return [];

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);

        $annotations = [self::TARGET_CLASS => [], self::TARGET_METHOD => [], self::TARGET_PROPERTY => []];
        $annotationNames = $this->normalizeNames($annotationNames);
        $annotationTargets = $this->normalizeTargets($annotationTargets, $annotationNames);

        //
        // Class not yet visited.. determine parent class
        if (!array_key_exists($reflClass->getName(), $this->classHierarchies)) {

            $this->classHierarchies[$reflClass->getName()] = null;
            if (($parentClassName = get_parent_class($reflClass->getName())))
                $this->classHierarchies[$reflClass->getName()] = $parentClassName;

            $this->setCache("/Hierarchies", $this->classHierarchies, true);
        }


        // Get class annotations
        if (in_array(self::TARGET_CLASS, $annotationTargets)) {

            $annotations[self::TARGET_CLASS][$reflClass->getName()] =
                $this->getClassAnnotations($reflClass, $annotationNames, $annotationTargets);
        }

        // Get method annotations
        if (in_array(self::TARGET_METHOD, $annotationTargets)) {

            $annotations[self::TARGET_METHOD][$reflClass->getName()] =
                $this->getMethodAnnotations($reflClass, $annotationNames, $annotationTargets);
        }

        // Get properties annotations
        if (in_array(self::TARGET_PROPERTY, $annotationTargets)) {

            $annotations[self::TARGET_PROPERTY][$reflClass->getName()] =
                $this->getPropertyAnnotations($reflClass, $annotationNames, $annotationTargets);
        }

        return $annotations;
    }
}
