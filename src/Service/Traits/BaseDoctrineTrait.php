<?php

namespace Base\Service\Traits;

use Base\Service\BaseService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

trait BaseDoctrineTrait
{
    /**
     * @var EntityManagerInterface
     */
    public static $entityManager;
    public static function setEntityManager(EntityManagerInterface $entityManager) { self::$entityManager = $entityManager; }
    public static function getEntityManager(bool $reopen = false): ?EntityManagerInterface
    {
        if (!BaseService::$entityManager) return null;
        if (!BaseService::$entityManager->isOpen()) {

            if(!$reopen) return null;
            BaseService::$entityManager = BaseService::$entityManager->create(
                BaseService::$entityManager->getConnection(), 
                BaseService::$entityManager->getConfiguration()
            );
        }

        return BaseService::$entityManager;
    }

    public function isWithinDoctrine()
    {
        $debug_backtrace = debug_backtrace();
        foreach($debug_backtrace as $trace)
            if(str_starts_with($trace["class"], "Doctrine")) return true;

        return false;
    }

    public function getOriginalEntityData($eventOrEntity, bool $reopen = false)
    { 
        $entity = $eventOrEntity->getObject();
        $originalEntityData = $this->getEntityManager($reopen)->getUnitOfWork()->getOriginalEntityData($entity);

        if($eventOrEntity instanceof PreUpdateEventArgs) {

            $event = $eventOrEntity;
            foreach($event->getEntityChangeSet() as $field => $data)
                $originalEntityData[$field] = $data[0];

        } else if($this->isWithinDoctrine()) {

            throw new \Exception("Achtung ! You are trying to access data object within a Doctrine method..".
                                "Original entity might have already been updated.");
        }

        return $originalEntityData;
    }

    protected static $entitySerializer = null;
    public function getOriginalEntity($eventOrEntity, bool $reopen = false)
    { 
        if(!self::$entitySerializer)
            self::$entitySerializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        $data = $this->getOriginalEntityData($eventOrEntity, $reopen);

        if(!$eventOrEntity instanceof LifecycleEventArgs) $entity = $eventOrEntity;
        else $entity = $eventOrEntity->getObject();

        dump($entity);
        return self::$entitySerializer->deserialize(json_encode($data), get_class($entity), 'json');
    }
}
