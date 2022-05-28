<?php

namespace Base\EntitySubscriber;

use Base\Database\Factory\EntityExtension;

use Base\Enum\EntityAction;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\Common\EventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ExtensionSubscriber implements EventSubscriberInterface
{
    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush, Events::postPersist
        ];
    }

    public function __construct(EntityManagerInterface $entityManager, EntityExtension $entityExtension)
    {
        $this->entityManager  = $entityManager;
        $this->entityExtension = $entityExtension;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    protected $scheduledEntityInsertions = [];
    protected $scheduledEntityUpdates    = [];
    protected $scheduledEntityDeletions  = [];

    public function onFlush(OnFlushEventArgs $event)
    {
        $uow = $event->getEntityManager()->getUnitOfWork();
        
        $this->scheduledEntityInsertions = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity)
            $this->scheduledEntityInsertions[] = $entity;

        $this->scheduledEntityUpdates = [];
        foreach ($uow->getScheduledEntityUpdates() as $entity)
            $this->scheduledEntityUpdates[] = $entity;
        foreach ($uow->getScheduledCollectionUpdates() as $entity)
            $this->scheduledEntityUpdates[] = $entity->getOwner();

        $this->scheduledEntityDeletions = [];
        foreach ($uow->getScheduledEntityDeletions() as $entity)
            $this->scheduledEntityDeletions[] = $entity;
        
        $this->scheduledEntityInsertions = array_unique_object($this->scheduledEntityInsertions);
        $this->scheduledEntityUpdates    = array_unique_object($this->scheduledEntityUpdates);
        $this->scheduledEntityDeletions  = array_unique_object($this->scheduledEntityDeletions);

        $this->entriesPendingForIds = $this->payload(EntityAction::INSERT, $this->scheduledEntityInsertions);
        $this->payload(EntityAction::UPDATE, $this->scheduledEntityUpdates);
        $this->payload(EntityAction::DELETE, $this->scheduledEntityDeletions);
    }

    public function postPersist(EventArgs $args)
    {
        $uow = $this->entityManager->getUnitOfWork();
        foreach($this->scheduledEntityInsertions as $entity) {

            $id = spl_object_id($entity);
            foreach($this->entriesPendingForIds[$id] ?? [] as $entry)
                $uow->scheduleExtraUpdate($entry, ['entityId' => [null, $entity->getId()]]);
        }
    }

    public function payload(string $action, array $entities)
    {  
        $uow = $this->entityManager->getUnitOfWork();

        $entries = [];
        foreach($entities as $entity) {

            $id = spl_object_id($entity);

            foreach($this->entityExtension->getExtensions() as $extension) {

                $matches = [];

                foreach($extension::get() as $column) {
                
                    list($className, $_) = explode("::", $column);
                    if(!is_instanceof($entity, $className)) continue;

                    $matches[$className] = $matches[$className] ?? [];
                    $matches[$className][] = $column;
                }

                foreach($matches as $className => $match) {

                    $properties = [];
                    foreach($match as $columns)
                        $properties[] = explode("::", $columns)[1];
                    
                    $array = $extension->payload($action, $className, $properties, $entity);

                    foreach($array as $entry) {

                        if($entry === null) continue;
                        if(!$entry->supports()) {
                        
                            if ($this->entityManager->contains($entry)) 
                                $this->entityManager->remove($entry);

                            continue;
                        }

                        $entry->setEntityClass($className);
                        $entry->setEntityId($entity->getId());
                        $entry->setAction($action);

                        switch($action) {

                            case EntityAction::INSERT:
                                $this->entityManager->persist($entry);
                                $uow->computeChangeSet($this->entityManager->getClassMetadata(get_class($entry)), $entry);
                                break;

                            case EntityAction::UPDATE:
                                if($this->entityManager->contains($entry))
                                    $uow->recomputeSingleEntityChangeSet($this->entityManager->getClassMetadata(get_class($entry)), $entry);
                                else {
                                    $this->entityManager->persist($entry);
                                    $uow->computeChangeSet($this->entityManager->getClassMetadata(get_class($entry)), $entry);    
                                }
                                break;

                            case EntityAction::DELETE:
                                if ($this->entityManager->contains($entry))
                                    $this->entityManager->remove($entry);
                                break;
                        }

                        if($entry) {
                            
                            if(!array_key_exists($id, $entries)) $entries[$id] = [];
                            $entries[$id][] = $entry;
                        }
                    }
                }
            }
        }

        return $entries;
    }
}
