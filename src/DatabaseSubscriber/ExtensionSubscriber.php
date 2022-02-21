<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Factory\EntityExtension;
use Base\Database\Factory\EntityHydrator;
use Base\Enum\EntityAction;
use Doctrine\Common\EventArgs;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ExtensionSubscriber implements EventSubscriber
{
    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::preFlush, Events::postPersist
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

    public function preFlush(PreFlushEventArgs $event)
    {
        $uow = $event->getEntityManager()->getUnitOfWork();
        $uow->computeChangeSets();

        foreach ($uow->getScheduledEntityInsertions() as $entity)
            $this->scheduledEntityInsertions[] = $entity;

        foreach ($uow->getScheduledEntityUpdates() as $entity)
            $this->scheduledEntityUpdates[] = $entity;

        foreach ($uow->getScheduledEntityDeletions() as $entity)
            $this->scheduledEntityDeletions[] = $entity;

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
        $entries = [];
        foreach($entities as $entity) {

                $id = spl_object_id($entity);
            foreach($this->entityExtension->getExtensions() as $extension) {

                $matches = [];

                foreach($extension::get() as $column) {
                
                    list($className, $_) = explode("::", $column);
                    if(!is_a($entity, $className)) continue;

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

                        $entry->setAction($action);
                        $entry->setEntityClass($className);
                        $entry->setEntityId($entity->getId());

                        switch($action) {

                            case EntityAction::INSERT:
                            case EntityAction::UPDATE:
                                if(!$this->entityManager->contains($entry))
                                    $this->entityManager->persist($entry);

                                break;

                            case EntityAction::DELETE:
                                $this->entityManager->remove($entry);
                                break;
                        }

                        $entries[$id] = $entry;
                    }
                }
            }
        }

        return $entries;
    }
}
