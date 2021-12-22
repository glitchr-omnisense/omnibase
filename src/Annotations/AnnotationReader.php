<?php

namespace Base\Annotations;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineAnnotationReader;
use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\ORM\EntityManager;
use Symfony\Component\ClassLoader\ClassMapGenerator;

use Base\Annotations\AbstractAnnotation;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Exception;

use Annotation as Test;
use Base\Service\BaseService;
use Base\Traits\SingletonTrait;
use Doctrine\ORM\Mapping\ClassMetadata;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\FlysystemBundle\Lazy\LazyFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AnnotationReader
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

    protected $parameterBag;

    public function __construct(
        EntityManager $entityManager, 
        ParameterBagInterface $parameterBag, 
        CacheInterface $cache,
        LazyFactory $lazyFactory, 
        RequestStack $requestStack)
    {
        if(!self::getInstance(false))
            self::setInstance($this);

        $this->doctrineReader = new DoctrineAnnotationReader();

        // Check if custom reader is enabled
        $this->parameterBag = $parameterBag;
        $this->enabled = $parameterBag->get("base.annotations.use_custom_reader");

        $paths = [];
        if ( ($matches = preg_grep('/^base.annotations.paths\.[0-9]*\.[.*]*$/', array_keys($parameterBag->all()))) )
            foreach ($matches as $match) $paths[] = $parameterBag->get($match);

        // Paths to look for annotations
        $paths[] = __DIR__ . "/Annotation";
        foreach ($paths as $path)
            $this->addPath($path);

        // Read from cache
        $this->cache   = $cache;

        $cacheName = "base.annotation_reader." . hash('md5', self::class);
        $this->cachePool['familyAnnotations']   = $this->cache->getItem($cacheName . ".familyAnnotations");
        $this->cachePool['classAnnotations']    = $this->cache->getItem($cacheName . ".classAnnotations");
        $this->cachePool['methodAnnotations']   = $this->cache->getItem($cacheName . ".methodAnnotations");
        $this->cachePool['propertyAnnotations'] = $this->cache->getItem($cacheName . ".propertyAnnotations");

        // Get entity manager for later use
        $this->entityManager = $entityManager;

        // Lazy factory for flysystem
        $this->lazyFactory = $lazyFactory;

        // Lazy factory for flysystem
        $this->requestStack = $requestStack;
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

    public function getProjectDir()
    {
        return dirname(__DIR__, 5);
    }

    /**
     * @var EntityManager
     */
    protected $entityManager = null;
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    public function getRepository($entity)
    {
        return $this->entityManager->getRepository($entity);
    }

    public function getParameterBag()
    {
        return $this->parameterBag;
    }

    public function getFilesystem(string $storage)
    {
        return $this->lazyFactory->createStorage($storage, $storage);
    }

    public function getFilesystemPathPrefixer($storage): PathPrefixer
    {
        $reflectionProperty = new \ReflectionProperty(LocalFilesystemAdapter::class, 'prefixer');
        $reflectionProperty->setAccessible(true);

        $adapter = $this->getFilesystemAdapter($storage);
        if($adapter instanceof LocalFilesystemAdapter)
            return $reflectionProperty->getValue($adapter);

        return null;
    }

    public function getFilesystemAdapter($storage): FilesystemAdapter
    {
        $reflectionProperty = new \ReflectionProperty(Filesystem::class, 'adapter');
        $reflectionProperty->setAccessible(true);

        if($storage instanceof FilesystemOperator)
            $filesystem = $storage;
        else if(is_string($storage))
            $filesystem = $this->getFilesystem($storage); 

        return $reflectionProperty->getValue($filesystem);
    }

    /**
     * @var SimpleAnnotationReader
     */
    protected $doctrineReader = null;
    public function getDoctrineReader()
    {
        return $this->doctrineReader;
    }

    /**
     * @var bool
     */
    protected bool $enabled;
    public function isEnabled(): bool { return $this->enabled; }

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

        foreach ($this->getAllClasses("", $path) as $class)
            $this->addKnownAnnotations($class);

        return $this;
    }

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


    protected $knownAnnotations = [];

    public function getKnownAnnotations()
    {
        return $this->knownAnnotations;
    }

    public function addKnownAnnotations(string $annotationName)
    {
        if (!is_subclass_of($annotationName, AbstractAnnotation::class))
            return $this;

        if (!in_array($annotationName, $this->knownAnnotations))
            $this->knownAnnotations[] = $annotationName;

        return $this;
    }

    public function removeKnownAnnotations(string $annotationName)
    {
        if (($pos = array_search($annotationName, $this->knownAnnotations)))
            unset($this->knownAnnotations[$pos]);

        return $this;
    }

    protected $targets = [];

    public function getTargets($className) // Annotation class
    {
        if(!is_subclass_of($className, AbstractAnnotation::class))
            throw new Exception("Class \"$className\" does not inherit from AbstractAnnotation");

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
            $annotations = $this->array_append_recursive(
                $annotations,
                $this->array_append_recursive(
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

        $annotations[$ancestor]  = $this->array_append_recursive(
            $this->getAnnotations($ancestor, $annotationNames, $targets),
            $this->getChildrenAnnotations($ancestor, $annotationNames, $targets)
        );

        if(!is_cli()) $this->cache->save($this->cachePool['familyAnnotations']->set($annotations));
        return $annotations[$ancestor] ?? [];
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
            $annotationNames = $this->getKnownAnnotations();

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
        }

        // If not covered by the target list
        if (!in_array(self::TARGET_CLASS, $targets))
            return [];

        if (empty($annotationNames))
            $annotationNames = $this->getKnownAnnotations();

        if ($classNameOrMetadataOrRefl instanceof ReflectionClass)
            $reflClass = $classNameOrMetadataOrRefl;
        else if ($classNameOrMetadataOrRefl instanceof ClassMetadata)
            $reflClass = $classNameOrMetadataOrRefl->getReflectionClass();
        else
            $reflClass = new \ReflectionClass($classNameOrMetadataOrRefl);

        // If annotation already computed
        $annotations = $this->cachePool['classAnnotations']->get() ?? [];

        if (!array_key_exists($reflClass->name, $annotations)) {

            // Compute the class annotations
            $annotations[$reflClass->name] = [];

            foreach ($this->getDoctrineReader()->getClassAnnotations($reflClass) as $annotation)
            {
                // Only look for AbstractAnnotation classes
                if (!is_subclass_of($annotation, AbstractAnnotation::class))
                    continue;
                if (!in_array(get_class($annotation), $this->getKnownAnnotations()))
                    continue;

                $annotations[$reflClass->name][] = $annotation;
            }
 
            if(!is_cli()) $this->cache->save($this->cachePool['classAnnotations']->set($annotations));
        }

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getKnownAnnotations())
            return $annotations[$reflClass->name];

        // Filter them ask request by the $annontationNames
        $filteredAnnotations = [];
        foreach($annotations[$reflClass->name] as $annotation) {

            if( in_array(get_class($annotation), $annotationNames) )
                $filteredAnnotations[] = $annotation;
        }

        return $filteredAnnotations;
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
            $annotationNames = $this->getKnownAnnotations();

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
        }

        // If not covered by the target list
        if (!in_array(self::TARGET_METHOD, $targets))
            return [];

        if ($classNameOrMetadataOrRefl instanceof ReflectionClass)
            $reflClass = $classNameOrMetadataOrRefl;
        else if ($classNameOrMetadataOrRefl instanceof ClassMetadata)
            $reflClass = $classNameOrMetadataOrRefl->getReflectionClass();
        else
            $reflClass = new \ReflectionClass($classNameOrMetadataOrRefl);

        // If annotation already computed
        $annotations = $this->cachePool['methodAnnotations']->get() ?? [];
        if (!array_key_exists($reflClass->name, $annotations)) {

            // Compute the class annotations
            $annotations[$reflClass->name] = [];
            foreach ($this->getKnownAnnotations() as $annotationName) {

                foreach ($reflClass->getMethods() as $reflMethod) {

                    if ( ($annotation = $this->getDoctrineReader()->getMethodAnnotation($reflMethod, $annotationName)) )
                        $annotations[$reflClass->name][$reflMethod->name][] = $annotation;
                }
            }

            if(!is_cli()) $this->cache->save($this->cachePool['methodAnnotations']->set($annotations));
        }

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getKnownAnnotations())
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
            $annotationNames = $this->getKnownAnnotations();

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
        }

        // If not covered by the target list
        if (!in_array(self::TARGET_PROPERTY, $targets))
            return [];

        if ($classNameOrMetadataOrRefl instanceof ReflectionClass)
            $reflClass = $classNameOrMetadataOrRefl;
        else if ($classNameOrMetadataOrRefl instanceof ClassMetadata)
            $reflClass = $classNameOrMetadataOrRefl->getReflectionClass();
        else
            $reflClass = new \ReflectionClass($classNameOrMetadataOrRefl);

        // If annotation already computed
        $annotations = $this->cachePool['propertyAnnotations']->get() ?? [];
        if (!array_key_exists($reflClass->name, $annotations)) {

            // Force to get all known annotations when buffering
            $annotations[$reflClass->name] = [];
            foreach ($this->getKnownAnnotations() as $annotationName) {

                foreach ($reflClass->getProperties() as $reflProperty) {

                    if( ($annotation = $this->getDoctrineReader()->getPropertyAnnotation($reflProperty, $annotationName)) )
                        $annotations[$reflClass->name][$reflProperty->name][] = $annotation;
                }
            }

            if(!is_cli()) $this->cache->save($this->cachePool['propertyAnnotations']->set($annotations));
        }

        // Return the full set of annotations for a given class
        if($annotationNames == $this->getKnownAnnotations())
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

    public function getAnnotations(?string $className, $annotationNames = null, array $targets = []): array
    {
        // Termination
        if ($className == null) return [];

        // Browsed classTypes (annotationNames)
         $annotationNames = array_unique(
            (is_array($annotationNames) ? $annotationNames :
            (is_object($annotationNames) ? [get_class($annotationNames)] :
            (is_string($annotationNames) ? [$annotationNames] : [])))
        );

        // Find targets corresponding to the annotationNames specified
        if (empty($annotationNames))
            $annotationNames = $this->getKnownAnnotations();

        // Compute target list
        if (empty($targets)) {

            foreach ($annotationNames as $annotationName)
                $targets = array_merge($targets, $this->getTargets($annotationName));

            $targets = array_unique($targets);
        }

        //
        // Class not yet visited.. determine parent class
        if (!array_key_exists($className, $this->hierarchy)) {

            $this->hierarchy[$className] = null;
            if (($parentClassName = get_parent_class($className)))
                $this->hierarchy[$className] = $parentClassName;
        }

        $reflClass = new \ReflectionClass($className);
        $annotations = [
            self::TARGET_CLASS    => [],
            self::TARGET_METHOD   => [],
            self::TARGET_PROPERTY => [],
        ];

        // Get class annotations
        if (in_array(self::TARGET_CLASS, $targets)) {

            $annotations[self::TARGET_CLASS][$className] =
                $this->getClassAnnotations($reflClass, $annotationNames, $targets);
        }

        // Get method annotations
        if (in_array(self::TARGET_METHOD, $targets)) {

            $annotations[self::TARGET_METHOD][$className] =
                $this->getMethodAnnotations($reflClass, $annotationNames, $targets);
        }

        // Get properties annotations
        if (in_array(self::TARGET_PROPERTY, $targets)) {

            $annotations[self::TARGET_PROPERTY][$className] =
                $this->getPropertyAnnotations($reflClass, $annotationNames, $targets);
        }

        return $annotations;
    }

    /* Useful function.. */
    public function array_append_recursive()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);

        if (!is_array($base)) $base = empty($base) ? array() : array($base);

        foreach ($arrays as $append) {
            if (!is_array($append)) $append = array($append);
            foreach ($append as $key => $value) {

                if (!array_key_exists($key, $base) and !is_numeric($key)) {
                    $base[$key] = $append[$key];
                    continue;
                }

                if (is_array($value) or is_array($base[$key])) {
                    $base[$key] = $this->array_append_recursive($base[$key], $append[$key]);
                } else if (is_numeric($key)) {
                    if (!in_array($value, $base)) $base[] = $value;
                } else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }
}
