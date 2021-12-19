<?php

namespace Base\DatabaseSubscriber;

use Base\Service\LocaleProviderInterface;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class TranslatableSubscriber implements EventSubscriber
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

    public function __construct(LocaleProviderInterface $localeProvider)
    {
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

        if ($fetchMode === 'EAGER') return ClassMetadataInfo::FETCH_EAGER;
        if ($fetchMode === 'EXTRA_LAZY') return ClassMetadataInfo::FETCH_EXTRA_LAZY;
        return ClassMetadataInfo::FETCH_LAZY;
    }

    private function mapTranslatable(ClassMetadataInfo $classMetadataInfo): void
    {
        if ($classMetadataInfo->hasAssociation('translations'))
            return;

        $classMetadataInfo->mapOneToMany([
            'fieldName' => 'translations',
            'mappedBy' => 'translatable',
            'indexBy' => self::LOCALE,
            'cascade' => ['persist', 'merge', 'remove'],
            'fetch' => $this->convertFetchString("LAZY"),
            'targetEntity' => $classMetadataInfo->getReflectionClass()
                ->getMethod('getTranslationEntityClass')
                ->invoke(null),
            'orphanRemoval' => true,
        ]);
    }

    private function mapTranslation(ClassMetadataInfo $classMetadataInfo): void
    {
        if(!$classMetadataInfo->hasAssociation('translatable')) {

            $classMetadataInfo->mapManyToOne([
                'fieldName'   => 'translatable',
                'inversedBy'  => 'translations',
                'cascade'     => ['persist', 'merge'],
                'fetch'       => $this->convertFetchString("LAZY"),
                'joinColumns' => [[
                    'name' => 'translatable_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                ]],
                'targetEntity' => $classMetadataInfo->getReflectionClass()
                    ->getMethod('getTranslatableEntityClass')
                    ->invoke(null),
            ]);
        }

        $name = $classMetadataInfo->getTableName() . '_unique_translation';
        if (!$this->hasUniqueTranslationConstraint($classMetadataInfo, $name) &&
            $classMetadataInfo->getName() == $classMetadataInfo->rootEntityName) {
            $classMetadataInfo->table['uniqueConstraints'][$name] = [
                'columns' => ['translatable_id', self::LOCALE]
            ];
        }

        if (! $classMetadataInfo->hasField(self::LOCALE) && ! $classMetadataInfo->hasAssociation(self::LOCALE)) {
            $classMetadataInfo->mapField([
                'fieldName' => self::LOCALE,
                'type' => 'string',
                'length' => 5,
            ]);
        }
    }

    private function hasUniqueTranslationConstraint(ClassMetadataInfo $classMetadataInfo, string $name): bool
    {
        if  (! isset($classMetadataInfo->table['uniqueConstraints'])) return false;
        return isset($classMetadataInfo->table['uniqueConstraints'][$name]);
    }
}
