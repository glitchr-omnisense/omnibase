<?php

namespace Base\DatabaseSubscriber;

use Base\Service\LocaleProviderInterface;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;

class IntlSubscriber implements EventSubscriber
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
        return [Events::loadClassMetadata, Events::prePersist, Events::preUpdate];
    }

    public function __construct(EntityManagerInterface $entityManager, LocaleProviderInterface $localeProvider)
    {
        $this->entityManager  = $entityManager;
        $this->localeProvider = $localeProvider;
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        if (is_subclass_of($event->getEntity(), TranslationInterface::class, true))
            $this->removeIfEmpty($event->getEntity());
    }

    public function preUpdate(LifecycleEventArgs $event)
    {

        if (is_subclass_of($event->getEntity(), TranslationInterface::class, true)) 
            $this->removeIfEmpty($event->getEntity());
    }

    private function removeIfEmpty(TranslationInterface $translation)
    {
        $translatable = $translation->getTranslatable();
        if($translation->isEmpty())
            $translatable->removeTranslation($translation);
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
        if (!$this->hasUniqueTranslationConstraint($classMetadata, $name))
            $classMetadata->table['uniqueConstraints'][$name] = ['columns' => ['translatable_id', self::LOCALE]];

        if(!$classMetadata->hasField(self::LOCALE) && ! $classMetadata->hasAssociation(self::LOCALE))
            $classMetadata->mapField(['fieldName' => self::LOCALE, 'type' => 'string', 'length' => 5]);
    }

    private function hasUniqueTranslationConstraint(ClassMetadata $classMetadata, string $name): bool
    {
        return isset($classMetadata->table['uniqueConstraints'][$name]);
    }
}
