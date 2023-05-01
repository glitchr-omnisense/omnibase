<?php

namespace Base\EntitySubscriber;

use Base\Database\Entity\EntityExtension;

use Base\Entity\Extension\Abstract\AbstractExtension;
use Base\Enum\EntityAction;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\Common\EventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use LogicException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 *
 */
class ExtensionSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var EntityExtension
     */
    protected EntityExtension $entityExtension;

    /**
     * @var PropertyAccessorInterface
     */
    protected PropertyAccessorInterface $propertyAccessor;

    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush, Events::postPersist, Events::loadClassMetadata
        ];
    }

    public function __construct(EntityManagerInterface $entityManager, EntityExtension $entityExtension)
    {
        $this->entityManager = $entityManager;
        $this->entityExtension = $entityExtension;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();
        if ($classMetadata->name != AbstractExtension::class) {
            return;
        }

        $namingStrategy = $this->entityManager->getConfiguration()->getNamingStrategy();

        $name = $namingStrategy->classToTableName(AbstractExtension::class) . '_unique';
        $classMetadata->table['uniqueConstraints'][$name]["columns"] = array_unique(array_merge(
            $classMetadata->table['uniqueConstraints'][$name]["columns"] ?? [],
            ["entityClass", "entityId"]
        ));
    }

    protected array $scheduledEntityInsertions = [];
    protected array $scheduledEntityUpdates = [];
    protected array $scheduledEntityDeletions = [];

    protected array $entriesPendingForIds = [];

    public function onFlush(OnFlushEventArgs $event)
    {
        $uow = $this->entityManager->getUnitOfWork();

        $this->scheduledEntityInsertions = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->scheduledEntityInsertions[] = $entity;
        }

        $this->scheduledEntityUpdates = [];
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->scheduledEntityUpdates[] = $entity;
        }
        foreach ($uow->getScheduledCollectionUpdates() as $entity) {
            $this->scheduledEntityUpdates[] = $entity->getOwner();
        }

        $this->scheduledEntityDeletions = [];
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->scheduledEntityDeletions[] = $entity;
        }

        $this->scheduledEntityInsertions = array_unique_object($this->scheduledEntityInsertions);
        $this->scheduledEntityUpdates = array_unique_object($this->scheduledEntityUpdates);
        $this->scheduledEntityDeletions = array_unique_object($this->scheduledEntityDeletions);

        $this->entriesPendingForIds = $this->payload(EntityAction::INSERT, $this->scheduledEntityInsertions);
        foreach ($this->payload(EntityAction::UPDATE, $this->scheduledEntityUpdates) as $id => $array) {
            $this->entriesPendingForIds[$id] = array_unique_object(array_merge($this->entriesPendingForIds[$id] ?? [], $array));
        }
        foreach ($this->payload(EntityAction::DELETE, $this->scheduledEntityDeletions) as $id => $array) {
            array_key_removes($this->entriesPendingForIds, $id);
        }
    }

    public function postPersist(EventArgs $args)
    {
        $newEntity = $args->getObject();
        if ($newEntity && $this->entityManager->getCache()) { // @WARN: Attempt to evict AbstractExtension..doesn't seems to be working.. TBD
            $this->entityManager->getCache()->evictEntity(get_class($newEntity), $newEntity->getId());
        }

        $uow = $this->entityManager->getUnitOfWork();

        $splObjectIdInsertions = array_map(fn($e) => spl_object_id($e), $this->scheduledEntityInsertions);
        $splObjectIdUpdates = array_map(fn($e) => spl_object_id($e), $this->scheduledEntityUpdates);
        foreach ($this->entriesPendingForIds as $id => $entries) {
            if (($key = array_search($id, $splObjectIdUpdates)) !== false) {
                $entity = $this->scheduledEntityUpdates[$key];
            } elseif (($key = array_search($id, $splObjectIdInsertions)) !== false) {
                $entity = $this->scheduledEntityInsertions[$key];
            } else {
                throw new LogicException("Entry pending for id not found in the scheduled entity");
            }

            foreach ($entries as $entry) {
                if (empty($entry->getEntityData())) {
                    $uow->scheduleForDelete($entry);
                } else {
                    $uow->scheduleExtraUpdate($entry, ['entityId' => [null, $entity->getId()]]);
                }
            }
        }
    }

    /**
     * @param string $action
     * @param array $entities
     * @return array
     * @throws \Exception
     */
    public function payload(string $action, array $entities)
    {
        $uow = $this->entityManager->getUnitOfWork();

        $entries = [];
        foreach ($entities as $entity) {
            $id = spl_object_id($entity);
            foreach ($this->entityExtension->getExtensions() as $extension) {
                $matches = [];

                foreach ($extension::get($entity) as $column) {
                    list($className, $_) = explode("::", $column);
                    if (!is_instanceof($entity, $className)) {
                        continue;
                    }

                    $matches[$className] = $matches[$className] ?? [];
                    $matches[$className][] = $column;
                }

                foreach ($matches as $className => $match) {
                    $properties = [];
                    foreach ($match as $columns) {
                        $properties[] = explode("::", $columns)[1];
                    }

                    $array = $extension->payload($action, $className, $properties, $entity);
                    foreach ($array as $entry) {
                        if ($entry === null) {
                            continue;
                        }
                        if (!$entry->supports()) {
                            if ($this->entityManager->contains($entry)) {
                                $this->entityManager->remove($entry);
                            }

                            continue;
                        }

                        $entry->setEntityClass($className);
                        $entry->setEntityId($entry->getEntityId() ?? $entity->getId());
                        $entry->setAction($action);
                        switch ($action) {
                            case EntityAction::INSERT:

                                $this->entityManager->persist($entry);
                                $uow->computeChangeSet($this->entityManager->getClassMetadata(get_class($entry)), $entry);
                                break;

                            case EntityAction::UPDATE:

                                if ($this->entityManager->contains($entry)) {
                                    $uow->recomputeSingleEntityChangeSet($this->entityManager->getClassMetadata(get_class($entry)), $entry);
                                } else {
                                    $this->entityManager->persist($entry);
                                    $uow->computeChangeSet($this->entityManager->getClassMetadata(get_class($entry)), $entry);
                                }
                                break;

                            case EntityAction::DELETE:
                                if ($this->entityManager->contains($entry)) {
                                    $this->entityManager->remove($entry);
                                }
                                break;
                        }

                        if (!array_key_exists($id, $entries)) {
                            $entries[$id] = [];
                        }
                        $entries[$id][] = $entry;
                    }
                }
            }
        }

        return $entries;
    }
}
