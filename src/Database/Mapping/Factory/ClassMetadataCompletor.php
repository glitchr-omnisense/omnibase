<?php

namespace Base\Database\Mapping\Factory;

use Base\Database\Mapping\ClassMetadataEnhanced;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

class ClassMetadataCompletor
{
    public function getAllClassNames() { return $this->entityManager->getMetadataFactory()->getAllClassNames(); }

    /**
     * Salt used by specific Object Manager implementation.
     *
     * @var string
     */
    protected $cacheSalt = '__CLASSMETADATA__ENHANCED__';

    protected static $cache;
    protected static array $loadedMetadata = [];

    public function __construct(EntityManagerInterface $entityManager, ?string $phpArrayFile = null)
    {
        $this->entityManager = $entityManager;

        if($phpArrayFile !== null) {

            $this->setCache(new PhpArrayAdapter($phpArrayFile, new FilesystemAdapter()));
            $this->warmUp();
        }
    }

    public function getCache(): ?CacheItemPoolInterface { return self::$cache; }
    public function setCache(CacheItemPoolInterface $cache)
    {
        self::$cache = $cache;
        return $this;
    }

    public function warmUp(): bool
    {
        if(!$this->getCache()) return false;

        foreach($this->getAllClassNames() as $className)
            $this->getMetadataFor($className);

        return true;
    }

    protected function getCacheKey(string $realClassName): string { return str_replace('\\', '__', $realClassName) . $this->cacheSalt; }
    protected function getMetadataFor(object|string $className, ?ClassMetadataInfo $classMetadata = null)
    {
        $className = is_object($className) ? get_class($className) : $className;
        if ( isset(self::$loadedMetadata[$this->getCacheKey($className)]) )
            return self::$loadedMetadata[$this->getCacheKey($className)];

        if ($this->getCache() === null) {

            self::$loadedMetadata[$this->getCacheKey($className)] = new ClassMetadataEnhanced($className, [], $classMetadata);
            return self::$loadedMetadata[$this->getCacheKey($className)];
        }

        $cachedPayload = $this->getCache()->getItem($this->getCacheKey($className))->get();
        if (!is_array($cachedPayload)) $cachedPayload =[];

        self::$loadedMetadata[$this->getCacheKey($className)] = new ClassMetadataEnhanced($className, $cachedPayload, $classMetadata);
        if($this->getCache()) {

            $item = $this->getCache()->getItem($this->getCacheKey($className));
            $item->set(self::$loadedMetadata[$this->getCacheKey($className)]->getPayload());

            $this->getCache()->saveDeferred($item);
        }

        if($classMetadata) self::$loadedMetadata[$this->getCacheKey($className)]->setClassMetadata($classMetadata);

        return self::$loadedMetadata[$this->getCacheKey($className)];
    }

    public function getClassMetadataEnhanced(ClassMetadataInfo $classMetadata)
    {
        return $this->getMetadataFor($classMetadata->name, $classMetadata);
    }

    public function saveCache(ClassMetadataEnhanced $data)
    {
        if(!$this->getCache()) return false;

        $item = $this->getCache()->getItem($this->getCacheKey($data->getName()));
        $item->set($data);

        return $this->getCache()->save($item);
    }
}