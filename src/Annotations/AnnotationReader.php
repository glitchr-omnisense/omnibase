<?php

namespace Base\Annotations;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineAnnotationReader;
use Doctrine\ORM\EntityManager;

use Base\Annotations\AbstractAnnotation;
use Base\Service\Filesystem;
use Base\Traits\BaseTrait;
use Exception;

use Base\Traits\SingletonTrait;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Contracts\Cache\CacheInterface;

class AnnotationReader
{
    use BaseTrait;
    use SingletonTrait;

    public const TARGET_CLASS    = "class";
    public const TARGET_METHOD   = "method";
    public const TARGET_PROPERTY = "property";

    public const ALL_TARGETS = [
        self::TARGET_CLASS,
        self::TARGET_METHOD,
        self::TARGET_PROPERTY
    ];

    protected $parameterBag;

    public function __construct(EventDispatcherInterface $eventDispatcher, EntityManager $entityManager, ParameterBagInterface $parameterBag, CacheInterface $cache, Filesystem $filesystem, RequestStack $requestStack, TokenStorageInterface $tokenStorage)
    {
        if(!self::getInstance(false))
            self::setInstance($this);

        $this->defaultReader = new DoctrineAnnotationReader();

        // Check if custom reader is enabled
        $this->parameterBag = $parameterBag;
        $this->enabled = $parameterBag->get("base.annotations.use_custom_reader");

        // Read from cache
        $this->cache   = $cache;

        $paths = [];
        $paths[] = __DIR__ . "/Annotation";
        $paths[] = __DIR__ . "/../Database/Annotation";
        if ( ($matches = preg_grep('/^base.annotations.paths\.[0-9]*\.[.*]*$/', array_keys($parameterBag->all()))) )
            foreach ($matches as $match) $paths[] = $parameterBag->get($match);

        // Paths to look for annotations
        foreach ($paths as $path)
            $this->addPath($path);

        $cacheName = "base.annotation_reader." . hash('md5', self::class);
        $this->cachePool['familyAnnotations']   = $this->cache->getItem($cacheName . ".familyAnnotations");
        $this->cachePool['classAnnotations']    = $this->cache->getItem($cacheName . ".classAnnotations");
        $this->cachePool['methodAnnotations']   = $this->cache->getItem($cacheName . ".methodAnnotations");
        $this->cachePool['propertyAnnotations'] = $this->cache->getItem($cacheName . ".propertyAnnotations");

        $this->entityManager   = $entityManager;
        $this->requestStack    = $requestStack;
        $this->tokenStorage    = $tokenStorage;
        $this->filesystem      = $filesystem;
        $this->eventDispatcher = $eventDispatcher;
    }

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

        foreach ($this->getAllClasses("", $path) as $annotation)
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
    
    public function getProjectDir() { return dirname(__DIR__, 5); }
    public function getUser() { return $this->tokenStorage->getUser(); }
    public function getImpersonator() 
    {
        $token = $this->tokenStorage->getToken();
        return ($token instanceof SwitchUserToken ? $token->getOriginalToken()->getUser() : null);
    }

    /**
     * @var EntityManager
     */
    protected $entityManager = null;
    public function getEntityManager() { return $this->entityManager; }
    public function getRepository($entity) { return $this->entityManager->getRepository($entity); }

    /**
     * @var Filesystem
     */
    protected $filesystem = null;
    public function getFilesystem(string $storage):Filesystem { return $this->filesystem->set($storage); }

    public function getParameterBag() { return $this->parameterBag; }

    /**
     * @var DoctrineAnnotationReader
     */
    protected $defaultReader = null;
    public function getDefaultReader() { return $this->defaultReader; }

    /**
     * @var bool
     */
    protected bool $enabled;
    public function isEnabled(): bool { return $this->enabled; }

    public static function getAllClasses($prefix, $path): array
    {
        $namespaces = [];

        $filenames = self::getFilenames($path);
        foreach ($filenames as $filename)
            $namespaces[] = self::getFullNamespace($filename, $prefix) . self::getClassname($filename);

        return $namespaces;
    }

    public static function getClassname($filename)
    {
        $directoriesAndFilename = explode('/', $filename);
        $filename = array_pop($directoriesAndFilename);

        $nameAndExtension = explode('.', $filename);
        $className = array_shift($nameAndExtension);

        return $className;
    }

    public static function getFullNamespace($filename, $prefix = "")
    {
        $lines = file($filename);
        $array = preg_grep('/^namespace /', $lines);
        $namespace = array_shift($array);

        $match = [];
        if (preg_match('/^namespace (\\\\?)' . addslashes($prefix) . '(\\\\?)(.*);$/', $namespace, $match)) {

            $array = array_pop($match);
            if (!empty($array)) return $array . "\\";
        }

        return "";
    }

    public static function getFilenames($path)
    {
        $filenames = [];

        $finderFiles = Finder::create()->files()->in($path)->name('*.php');
        foreach ($finderFiles as $finderFile)
            $filenames[] = $finderFile->getRealpath();

        return $filenames;
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

    protected array $targets = [];
    public function getTargets($className): array // Annotation class
    {
        $className = (is_object($className) ? get_class($className) : $className);
        if (!array_key_exists($className, $this->targets)) {

            $reflClass = new \ReflectionClass($className);

            $targets = [];
            if (preg_match_all('/@Target\(\{(.*)\}\)/', $reflClass->getDocComment(), $matches, PREG_SET_ORDER))
                $targets = json_decode(mb_strtolower("[" . end($matches)[1] . "]"));

            foreach ($targets as $target) {

                switch ($target) {

                    case 'all':
                        $this->targets[$className][] = "class";
                        $this->targets[$className][] = "method";
                        $this->targets[$className][] = "property";

                        // Not used at this time by the custom AnnotationReader
                        $this->targets[$className][] = "annotation";
                        $this->targets[$className][] = "function";
                        break;

                    case 'class':
                    case 'method':
                    case 'property':
                    case 'annotation':
                    case 'function':
                        $this->targets[$className][] = $target;
                        break;

                    default:
                        throw new Exception("Unexpected @Target parameter in " . $className);
                }
            }
        }

        return $this->targets[$className] ?? [];
    }

    /**
     * Everything related to hierarchy
     *
     * @var array
     */
    protected $hierarchy   = [];
    public function getParent($className): ?string { return $this->hierarchy[$className] ?? null; }
    public function getAncestor($className): ?string
    {
        $ancestor = $this->getParent($className);
        while ($className = $this->getParent($className)) {

            $ancestor = $className;
        }

        return $ancestor;
    }
    public function getAncestorAnnotations($className, $annotationNames = null, $targets = [])
    {
        $ancestor = $this->getAncestor($className);
        return $this->getAnnotations($ancestor, $annotationNames, $targets);
    }

    public function getParentAnnotations($className, $annotationNames = null, $targets = [])
    {
        $parent = $this->getParent($className);
        return $this->getAnnotations($parent, $annotationNames, $targets);
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

    public function getChildrenAnnotations($className, $annotationNames = null, $targets = [])
    {
        $annotations = [];
        foreach ($this->getChildren($className) as $child) {

            $childrenAnnotations = $this->getChildrenAnnotations($child, $annotationNames, $targets);
            $annotations = array_append_recursive(
                $annotations,
                array_append_recursive(
                    $this->getAnnotations($child, $annotationNames, $targets),
                    $childrenAnnotations
                )
            );
        }

        return $annotations;
    }

    public function getFamily($className)
    {
        $ancestor = $this->getAncestor($className) ?? $className;
        $children = $this->getChildren($ancestor);

        return array_merge_recursive($ancestor, $children);
    }

    public function getFamilyAnnotations($className, $annotationNames = null, $targets = [])
    {
        $ancestor = $this->getAncestor($className) ?? $className;

        $annotations = $this->cachePool['familyAnnotations']->get() ?? [];
        if (array_key_exists($ancestor, $annotations))
            return $annotations[$ancestor];

        $annotations[$ancestor]  = array_append_recursive(
            $this->getAnnotations($ancestor, $annotationNames, $targets),
            $this->getChildrenAnnotations($ancestor, $annotationNames, $targets)
        );

        if(!is_cli()) $this->cache->save($this->cachePool['familyAnnotations']->set($annotations));
        return $annotations[$ancestor] ?? [];
    }


    public function getDefaultClassAnnotations($classNameOrMetadataOrRefl)
    {
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        return $this->getDefaultReader()->getClassAnnotations($reflClass);
    }

    public function getClassAnnotations($classNameOrMetadataOrRefl, $annotationNames = null, array $targets = []): array
    {
        // Browsed classTypes (annotationNames)
         $annotationNames = array_unique(
            (is_array($annotationNames) ? $annotationNames :
            (is_object($annotationNames) ? [get_class($annotationNames)] :
            (is_string($annotationNames) ? [$annotationNames] : [])))
        );

        // Find targets corresponding to the annotationNames specified
        if (empty($annotationNames))
            $annotationNames = $this->getAnnotationNames();

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
            if (empty($targets)) $targets = self::ALL_TARGETS;
        }

        // If not covered by the target list
        if (!in_array(self::TARGET_CLASS, $targets))
            return [];

        if (empty($annotationNames))
            $annotationNames = $this->getAnnotationNames();
        
        // If annotation already computed
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        $annotations = $this->cachePool['classAnnotations']->get() ?? [];
        if (!array_key_exists($reflClass->name, $annotations)) {

            // Compute the class annotations
            $annotations[$reflClass->name] = [];

            foreach ($this->getDefaultReader()->getClassAnnotations($reflClass) as $annotation) {

                // Only look for AbstractAnnotation classes
                if (!is_subclass_of($annotation, AbstractAnnotation::class))
                    continue;
                if (!in_array(get_class($annotation), $this->getAnnotationNames()))
                    continue;

                $annotations[$reflClass->name][] = $annotation;
            }
 
            if(!is_cli()) $this->cache->save($this->cachePool['classAnnotations']->set($annotations));
        }

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getAnnotationNames())
            return $annotations[$reflClass->name];

        // Filter them ask request by the $annontationNames
        $filteredAnnotations = [];
        foreach($annotations[$reflClass->name] as $annotation) {

            if( in_array(get_class($annotation), $annotationNames) )
                $filteredAnnotations[] = $annotation;
        }

        return $filteredAnnotations;
    }

    public function getDefaultMethodAnnotations($classNameOrMetadataOrRefl)
    {
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);

        $annotations = [];
        foreach ($reflClass->getMethods() as $reflMethod)
            $annotations[$reflMethod->getName()] = $this->getDefaultReader()->getMethodAnnotations($reflMethod);
        
        return array_filter($annotations);
    }

    public function getMethodAnnotations($classNameOrMetadataOrRefl, array $annotationNames = [], array $targets = []): array
    {
        // Browsed classTypes (annotationNames)
         $annotationNames = array_unique(
            (is_array($annotationNames) ? $annotationNames :
            (is_object($annotationNames) ? [get_class($annotationNames)] :
            (is_string($annotationNames) ? [$annotationNames] : [])))
        );

        // Find targets corresponding to the annotationNames specified
        if (empty($annotationNames))
            $annotationNames = $this->getAnnotationNames();

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
            if (empty($targets)) $targets = self::ALL_TARGETS;
        }

        // If not covered by the target list
        if (!in_array(self::TARGET_METHOD, $targets))
            return [];

        // If annotation already computed
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        $annotations = $this->cachePool['methodAnnotations']->get() ?? [];
        if (!array_key_exists($reflClass->name, $annotations)) {

            // Compute the class annotations
            $annotations[$reflClass->name] = [];
            foreach ($this->getAnnotationNames() as $annotationName) {

                foreach ($reflClass->getMethods() as $reflMethod) {

                    if ( ($annotation = $this->getDefaultReader()->getMethodAnnotation($reflMethod, $annotationName)) )
                        $annotations[$reflClass->name][$reflMethod->name][] = $annotation;
                }
            }

            if(!is_cli()) $this->cache->save($this->cachePool['methodAnnotations']->set($annotations));
        }

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getAnnotationNames())
            return $annotations[$reflClass->name];

        // Filter them ask request by the $annontationNames
        $filteredAnnotations = [];
        foreach($annotations[$reflClass->name] as $method => $array) {

            foreach($array as $annotation) {

                if( in_array(get_class($annotation), $annotationNames) )
                    $filteredAnnotations[$method][] = $annotation;
            }
        }
        return $filteredAnnotations;
    }

    public function getDefaultPropertyAnnotations($classNameOrMetadataOrRefl)
    {
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);

        $annotations = [];
        foreach ($reflClass->getProperties() as $reflProperty)
            $annotations[$reflProperty->getName()] = $this->getDefaultReader()->getPropertyAnnotations($reflProperty);
        
        return array_filter($annotations);
    }

    public function getPropertyAnnotations($classNameOrMetadataOrRefl, $annotationNames = null, array $targets = []): array
    {
        // Browsed classTypes (annotationNames)
         $annotationNames = array_unique(
            (is_array($annotationNames) ? $annotationNames :
            (is_object($annotationNames) ? [get_class($annotationNames)] :
            (is_string($annotationNames) ? [$annotationNames] : [])))
        );

        // Find targets corresponding to the annotationNames specified
        if (empty($annotationNames))
            $annotationNames = $this->getAnnotationNames();

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
            if (empty($targets)) $targets = self::ALL_TARGETS;
        }

        // If not covered by the target list
        if (!in_array(self::TARGET_PROPERTY, $targets))
            return [];

        // If annotation already computed
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        $annotations = $this->cachePool['propertyAnnotations']->get() ?? [];
        if (!array_key_exists($reflClass->name, $annotations)) {

            // Force to get all known annotations when buffering
            $annotations[$reflClass->name] = [];
            foreach ($this->getAnnotationNames() as $annotationName) {

                foreach ($reflClass->getProperties() as $reflProperty) {

                    if( ($annotation = $this->getDefaultReader()->getPropertyAnnotation($reflProperty, $annotationName)) )
                        $annotations[$reflClass->name][$reflProperty->name][] = $annotation;
                }
            }

            if(!is_cli()) $this->cache->save($this->cachePool['propertyAnnotations']->set($annotations));
        }

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getAnnotationNames())
            return $annotations[$reflClass->name];

        // Filter them ask request by the $annontationNames
        $filteredAnnotations = [];
        foreach($annotations[$reflClass->name] as $property => $array) {

            foreach($array as $annotation) {

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

    public function getDefaultAnnotations($classNameOrMetadataOrRefl, $annotationNames = null, array $targets = []): array
    {
        // Termination
        if ($classNameOrMetadataOrRefl == null) return [];

        // Browsed classTypes (annotationNames)
         $annotationNames = array_unique(
            (is_array($annotationNames) ? $annotationNames :
            (is_object($annotationNames) ? [get_class($annotationNames)] :
            (is_string($annotationNames) ? [$annotationNames] : [])))
        );

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
            if (empty($targets)) $targets = self::ALL_TARGETS;
        }

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        $annotations = [
            self::TARGET_CLASS    => [],
            self::TARGET_METHOD   => [],
            self::TARGET_PROPERTY => [],
        ];

        // Get class annotations
        if (in_array(self::TARGET_CLASS, $targets)) {

            $annotations[self::TARGET_CLASS][$reflClass->getName()] =
                $this->getDefaultClassAnnotations($reflClass, $annotationNames, $targets);
        }

        // Get method annotations
        if (in_array(self::TARGET_METHOD, $targets)) {

            $annotations[self::TARGET_METHOD][$reflClass->getName()] =
                $this->getDefaultMethodAnnotations($reflClass, $annotationNames, $targets);
        }

        // Get properties annotations
        if (in_array(self::TARGET_PROPERTY, $targets)) {

            $annotations[self::TARGET_PROPERTY][$reflClass->getName()] =
                $this->getDefaultPropertyAnnotations($reflClass, $annotationNames, $targets);
        }

        return $annotations;
    }

    public function getAnnotations($classNameOrMetadataOrRefl, $annotationNames = null, array $targets = []): array
    {
        // Termination
        if ($classNameOrMetadataOrRefl == null) return [];

        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        $annotations = [
            self::TARGET_CLASS    => [],
            self::TARGET_METHOD   => [],
            self::TARGET_PROPERTY => [],
        ];

        // Browsed classTypes (annotationNames)
         $annotationNames = array_unique(
            (is_array($annotationNames) ? $annotationNames :
            (is_object($annotationNames) ? [get_class($annotationNames)] :
            (is_string($annotationNames) ? [$annotationNames] : [])))
        );

        // Find targets corresponding to the annotationNames specified
        if (empty($annotationNames))
            $annotationNames = $this->getAnnotationNames();

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
            if (empty($targets)) $targets = self::ALL_TARGETS;
        }
        
        $reflClass = $this->getReflClass($classNameOrMetadataOrRefl);
        $annotations = [
            self::TARGET_CLASS    => [],
            self::TARGET_METHOD   => [],
            self::TARGET_PROPERTY => [],
        ];

        //
        // Class not yet visited.. determine parent class
        if (!array_key_exists($reflClass->getName(), $this->hierarchy)) {

            $this->hierarchy[$reflClass->getName()] = null;
            if (($parentClassName = get_parent_class($reflClass->getName())))
                $this->hierarchy[$reflClass->getName()] = $parentClassName;
        }

        // Get class annotations
        if (in_array(self::TARGET_CLASS, $targets)) {

            $annotations[self::TARGET_CLASS][$reflClass->getName()] =
                $this->getClassAnnotations($reflClass, $annotationNames, $targets);
        }

        // Get method annotations
        if (in_array(self::TARGET_METHOD, $targets)) {

            $annotations[self::TARGET_METHOD][$reflClass->getName()] =
                $this->getMethodAnnotations($reflClass, $annotationNames, $targets);
        }

        // Get properties annotations
        if (in_array(self::TARGET_PROPERTY, $targets)) {

            $annotations[self::TARGET_PROPERTY][$reflClass->getName()] =
                $this->getPropertyAnnotations($reflClass, $annotationNames, $targets);
        }

        return $annotations;
    }
}
