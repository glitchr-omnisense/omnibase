<?php

namespace Base\DatabaseSubscriber;

use Doctrine\ORM\EntityManager;

use Base\BaseBundle;
use Base\Service\LocaleProviderInterface;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Base\Database\Walker\TranslatableWalker;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use InvalidArgumentException;

class IntlSubscriber implements EventSubscriberInterface
{
    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [Events::loadClassMetadata, Events::postLoad, Events::onFlush];
    }

    public function getLocaleProvider() { return $this->localeProvider; }

    public function __construct(EntityManagerInterface $entityManager, LocaleProviderInterface $localeProvider)
    {
        $this->entityManager  = $entityManager;
        $this->localeProvider = $localeProvider;
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $uow = $this->entityManager->getUnitOfWork();

        $translation = $args->getObject();
        if (is_subclass_of($translation, TranslationInterface::class, true)) {

            if ($translation->isEmpty()) // Mark as removal for mispersistent translations..
                $uow->scheduleOrphanRemoval($translation);
        }
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $uow = $this->entityManager->getUnitOfWork();

        $scheduledEntities = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity)
            $scheduledEntities[] = $entity;
        foreach ($uow->getScheduledEntityUpdates() as $entity)
            $scheduledEntities[] = $entity;
        foreach ($uow->getScheduledCollectionUpdates() as $entity)
            $scheduledEntities[] = $entity->getOwner();

        // Retrieve translatable objects
        $scheduledEntities = array_filter(
            array_unique_object($scheduledEntities),
            fn($e) => $e instanceof TranslationInterface || $e instanceof TranslatableInterface
        );

        // Normalize and turn into orphan intl entities if empty
        foreach(array_unique_object($scheduledEntities) as $entity)
            $this->normalize($entity);
    }

    protected function normalize(TranslationInterface|TranslatableInterface $entity)
    {
        $uow = $this->entityManager->getUnitOfWork();

        if($entity instanceof TranslatableInterface) {

            foreach($entity->getTranslations() as $locale => $translation) {

                if($translation->getLocale() === null) $translation->setLocale($locale);
                if($translation->getLocale() !== null && $translation->getLocale() !== $locale)
                    throw new InvalidArgumentException("Unexpected locale \"".$translation->getLocale()."\" found with respect to collection key \"".$locale."\".");

                if(!$translation->getTranslatable())
                    $translation->setTranslatable($entity);
            }
        }

        if($entity instanceof TranslationInterface) {

            if (!$entity->isEmpty()) $uow->cancelOrphanRemoval($entity);
            else {

                $translatable = $entity->getTranslatable();
                if($translatable) $translatable->removeTranslation($entity);

                if($this->entityManager->contains($entity)) {
                    $this->entityManager->remove($entity);
                }
            }
        }

        return $this;
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $loadClassMetadataEventArgs): void
    {
        $classMetadata = $loadClassMetadataEventArgs->getClassMetadata();

        if ($classMetadata->reflClass === null)
            return; // Class has not yet been fully built, ignore this event

        if ($classMetadata->isMappedSuperclass) return;

        if (is_subclass_of($classMetadata->reflClass->getName(), TranslatableInterface::class, true))
            $this->mapTranslatable($classMetadata);
        if (is_subclass_of($classMetadata->reflClass->getName(), TranslationInterface::class, true))
            $this->mapTranslation($classMetadata);
    }

    /**
     * Convert string FETCH mode to required string
     */
    private function convertFetchString($fetchMode): int
    {
        if (is_int($fetchMode))
            return $fetchMode;

        switch($fetchMode) {
            case 'EAGER':
                return ClassMetadata::FETCH_EAGER;

            case 'EXTRA_LAZY':
                return ClassMetadata::FETCH_EXTRA_LAZY;

            default:
            case 'LAZY':
                return ClassMetadata::FETCH_LAZY;
        }
    }

    private function mapTranslatable(ClassMetadata $classMetadata): void
    {
        $targetEntity = $classMetadata->getReflectionClass()->getMethod('getTranslationEntityClass')->invoke(null);
        if($classMetadata->hasAssociation('translations')) {

            $mapping = $classMetadata->getAssociationMapping("translations");
            if(is_subclass_of($targetEntity, $mapping["targetEntity"] ?? null)) {

                $classMetadata->associationMappings["translations"]["targetEntity"] = $targetEntity;
                $classMetadata->associationMappings["translations"]["sourceEntity"] = $classMetadata->getName();

                $classMetadata->cache = $classMetadata->cache ?? null;
                $classMetadata->cache = [
                    "region" => $this->entityManager->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->getName()),
                    "usage" => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
                ];

                $classMetadata->associationMappings["translations"]["cache"] = $classMetadata->cache ?? null;
                $classMetadata->associationMappings["translations"]["cache"] = [
                    "region" => $this->entityManager->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->getName()),
                    "usage" => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
                ];
            }

        } else {

            $classMetadata->cache = $classMetadata->cache ?? null;
            $classMetadata->cache = [
                "region" => $this->entityManager->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->getName()),
                "usage" => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
            ];

            $classMetadata->mapOneToMany([
                'fieldName' => 'translations',
                'mappedBy' => 'translatable',
                'cache' => [
                    "region" => $this->entityManager->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->getName())."__translations",
                    "usage" => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
                ],
                'indexBy' => TranslatableWalker::LOCALE,
                'cascade' => ['persist', 'merge', 'remove'],
                'fetch' => $this->convertFetchString("LAZY"),
                'targetEntity' => $targetEntity,
                'orphanRemoval' => true,
            ]);
        }
    }

    private function mapTranslation(ClassMetadata $classMetadata): void
    {
        $targetEntity = $classMetadata->getReflectionClass()->getMethod('getTranslatableEntityClass')->invoke(null);
        $targetClassMetadata = $this->entityManager->getClassMetadata($targetEntity);

        if($classMetadata->hasAssociation('translatable')) {

            $mapping = $classMetadata->getAssociationMapping("translatable");
            if(is_subclass_of($targetEntity, $mapping["targetEntity"] ?? null)) {

                $classMetadata->associationMappings["translatable"]["targetEntity"] = $targetEntity;
                $classMetadata->associationMappings["translatable"]["sourceEntity"] = $classMetadata->getName();
                $classMetadata->cache = $classMetadata->cache ?? null;
                $classMetadata->cache = [
                    "region" => $this->entityManager->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->getName()),
                    "usage" => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
                ];

            }

        } else {

            $classMetadata->cache = $classMetadata->cache ?? null;
            $classMetadata->cache = [
                "region" => $this->entityManager->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->getName()),
                "usage" => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
            ];

            $classMetadata->mapManyToOne([
                'fieldName'   => 'translatable',
                'inversedBy'  => 'translations',
                'cache' => BaseBundle::CACHE ? [
                    "region" => $this->entityManager->getConfiguration()->getNamingStrategy()->classToTableName($classMetadata->getName())."__translatable",
                    "usage" => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
                ] : null,
                'cascade'     => ['persist', 'merge'],
                'fetch'       => $this->convertFetchString("LAZY"),
                'joinColumns' => [[
                    'name' => TranslatableWalker::FOREIGN_KEY,
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                ]],
                'targetEntity' => $classMetadata->getReflectionClass()
                    ->getMethod('getTranslatableEntityClass')
                    ->invoke(null),
            ]);
        }

        $classMetadata->cache = $targetClassMetadata->cache;
        if(array_key_exists("region", $classMetadata->cache ?? []))
            $classMetadata->cache["region"] .= "_translation";

        $namingStrategy = $this->entityManager->getConfiguration()->getNamingStrategy();
        $name = $namingStrategy->classToTableName($classMetadata->rootEntityName) . '_' .TranslatableWalker::SALT;

        if ($classMetadata->getName() == $classMetadata->rootEntityName) {

            $classMetadata->table['uniqueConstraints'][$name] ??= [];
            $classMetadata->table['uniqueConstraints'][$name]["columns"] = array_unique(array_merge(
                $classMetadata->table['uniqueConstraints'][$name]["columns"] ?? [],
                [TranslatableWalker::FOREIGN_KEY, TranslatableWalker::LOCALE]
            ));
        }

        if(!$classMetadata->hasField(TranslatableWalker::LOCALE) && !$classMetadata->hasAssociation(TranslatableWalker::LOCALE))
            $classMetadata->mapField(['fieldName' => TranslatableWalker::LOCALE, 'type' => 'string', 'length' => 5]);
    }
}
