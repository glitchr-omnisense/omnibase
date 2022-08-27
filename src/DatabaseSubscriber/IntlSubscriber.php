<?php

namespace Base\DatabaseSubscriber;

use Base\Service\LocaleProviderInterface;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use InvalidArgumentException;

class IntlSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    public const LOCALE = 'locale';

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
        $scheduledEntities = array_filter(array_unique_object($scheduledEntities));
        foreach(array_unique_object($scheduledEntities) as $entity) {

            if (is_subclass_of($entity, TranslationInterface::class, true))
                $scheduledEntities[] = $entity->getTranslatable();
        }

        // Keep unique translatable only
        $scheduledTranslatables = array_filter(
            array_unique_object($scheduledEntities),
            fn($e) => is_subclass_of($e, TranslatableInterface::class, true)
        );

        // Normalize and turn orphan Intl entities if empty
        foreach($scheduledTranslatables as $translatable)
            $this->normalize($translatable);

    }

    protected function normalize(TranslatableInterface $translatable)
    {
        $uow = $this->entityManager->getUnitOfWork();

        foreach($translatable->getTranslations() as $locale => $translation) {

            if($translation->getLocale() === null) $translation->setLocale($locale);
            if($translation->getLocale() !== null && $translation->getLocale() !== $locale)
                throw new InvalidArgumentException("Unexpected locale \"".$translation->getLocale()."\" found with respect to collection key \"".$locale."\".");

            if(!$translation->getTranslatable())
                $translation->setTranslatable($translatable);

            $translatable = $translation->getTranslatable();
            if ($translatable && !$translation->isEmpty())
                $uow->cancelOrphanRemoval($translation);
        }
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
            }

        } else {

            $classMetadata->mapOneToMany([
                'fieldName' => 'translations',
                'mappedBy' => 'translatable',
                'indexBy' => self::LOCALE,
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
            }

        } else {

            $classMetadata->mapManyToOne([
                'fieldName'   => 'translatable',
                'inversedBy'  => 'translations',
                'cascade'     => ['persist', 'merge'],
                'fetch'       => $this->convertFetchString("LAZY"),
                'joinColumns' => [[
                    'name' => 'translatable_id',
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
        $name = $namingStrategy->classToTableName($classMetadata->rootEntityName) . '_unique_translation';

        if ($classMetadata->getName() == $classMetadata->rootEntityName && !$this->hasUniqueTranslationConstraint($classMetadata, $name))
            $classMetadata->table['uniqueConstraints'][$name] = ['columns' => ['translatable_id', self::LOCALE]];

        if(!$classMetadata->hasField(self::LOCALE) && ! $classMetadata->hasAssociation(self::LOCALE))
            $classMetadata->mapField(['fieldName' => self::LOCALE, 'type' => 'string', 'length' => 5]);
    }

    private function hasUniqueTranslationConstraint(ClassMetadata $classMetadata, string $name): bool
    {
        return isset($classMetadata->table['uniqueConstraints'][$name]);
    }
}
