<?php

namespace Base\Database\Mapping;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

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
    protected static array $loadedMetadataCompletor = [];

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
            $this->getMetadataCompletorFor($className);

        return true;
    }

    protected function getCacheKey(string $realClassName): string { return str_replace('\\', '__', $realClassName) . $this->cacheSalt; }
    protected function getMetadataCompletorFor(object|string $className)
    {
        $className = is_object($className) ? get_class($className) : $className;
        if ( isset(self::$loadedMetadataCompletor[$this->getCacheKey($className)]) ) {
            return self::$loadedMetadataCompletor[$this->getCacheKey($className)];
        }

        if ($this->getCache() === null) {
            self::$loadedMetadataCompletor[$this->getCacheKey($className)] = new ClassMetadataCompletor();
            return self::$loadedMetadataCompletor[$this->getCacheKey($className)];
        }

        $cached = $this->getCache()->getItem($this->getCacheKey($className))->get();
        if ($cached instanceof ClassMetadataCompletor) {

            self::$loadedMetadataCompletor[$this->getCacheKey($className)] = $cached;
            return self::$loadedMetadataCompletor[$this->getCacheKey($className)];
        }

        self::$loadedMetadataCompletor[$this->getCacheKey($className)] = new ClassMetadataCompletor();

        return self::$loadedMetadataCompletor[$this->getCacheKey($className)];
    }

    public function saveCache(ClassMetadataCompletor $data)
    {
        if(!$this->getCache()) return false;

        $item = $this->getCache()->getItem($this->getCacheKey($data->getName()));
        $item->set($data);

        return $this->getCache()->save($item);
    }
}