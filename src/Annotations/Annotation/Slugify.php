<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Class Slugify
 * package Base\Annotations\Annotation\Slugify
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("reference", type = "string"),
 *   @Attribute("updatable", type = "bool"),
 *   @Attribute("unique",    type = "bool"),
 *
 *   @Attribute("length",     type = "integer"),
 *   @Attribute("zeros",     type = "integer"),
 * 
 *   @Attribute("locale",     type = "string"),
 *   @Attribute("map",        type = "array"),
 *   @Attribute("separator",  type = "string"),
 *   @Attribute("exception",  type = "string"),
 *   @Attribute("lowercase",  type = "bool")
 * })
 */
class Slugify extends AbstractAnnotation
{
    protected $slugger;
    protected bool $unique;
    protected bool $updatable;
    protected bool $lowercase;

    protected string $separator;

    public function __construct( array $data )
    {
        $this->referenceColumn = $data['reference'] ?? null;

        $this->updatable = $data['updatable'] ?? false; // TODO: IMPLEMENT
        $this->unique    = $data['unique']    ?? true;

        $this->zeros = $data['zeros'] ?? 0; // TODO: IMPLEMENT
        $this->length = $data['length'] ?? null; // TODO: IMPLEMENT

        $this->separator = $data['separator'] ?? '-';
        $this->exception = $data['exception'] ?? null;
        $this->lowercase = $data['lowercase'] ?? true;
        $this->slugger   = new AsciiSlugger(
            $data["locale"] ?? null,
            $data["map"]    ?? null
        );
    }

    public function isUpdatable()
    {
        return $this->updatable;
    }

    public function getReferenceColumn()
    {
        return $this->referenceColumn;
    }

    public function getInvalidSlugs($event, $entity, $property) 
    {
        $uow = $event->getEntityManager()->getUnitOfWork();

        $candidateEntities = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity2)
            $candidateEntities[] = $entity2;
        foreach ($uow->getScheduledEntityUpdates() as $entity2)
            $candidateEntities[] = $entity2;

        $invalidSlugs = [];
        foreach ($candidateEntities as $entity2) {

            if($entity === $entity2) break; // FIFO
            if(!property_exists($entity2, $property)) continue;

            $propertyDeclarer  = property_declarer($entity , $property);
            $propertyDeclarer2 = property_declarer($entity2, $property);
            if($propertyDeclarer != $propertyDeclarer2 && !is_a($propertyDeclarer, $propertyDeclarer2)) continue;

            $invalidSlugs[] = $this->getPropertyValue($entity2, $property);
        }

        $firstEntity = begin($candidateEntities);
        if($firstEntity === $entity) {
            $firstSlug = $this->getPropertyValue($entity, $property);
            $invalidSlugs = array_filter($invalidSlugs, fn($s) => $s !== $firstSlug);
        }

        return $invalidSlugs;
    }
    
    public function slug($entity, ?string $input = null, string $suffix = ""): string
    {
        // Check if field already set.. get field value or by default class name
        if(!$input && $this->referenceColumn) $input = $this->getPropertyValue($entity, $this->referenceColumn);
        if(!$input) $input = camel_to_snake(class_basename($entity), "-");

        $input .= !empty($suffix) ? $this->separator.$suffix : "";

        $slug = $this->exception
            ? implode($this->exception, array_map(fn($i) => $this->slugger->slug($i, $this->separator), explode($this->exception, $input)))
            : $this->slugger->slug($input, $this->separator);

        return ($this->lowercase ? strtolower($slug) : $slug);
    }
    
    public function getSlug($entity, string $property, ?string $defaultInput = null, array $invalidSlugs = []): string
    {
        $repository  = $this->getPropertyOwnerRepository($entity, $property);
        $defaultSlug = $this->slug($entity, $defaultInput);

        $slug = $defaultSlug;
        if(!$this->unique) return $slug;
        
        for($i = 1; $repository->findOneBy([$property => $slug]) || in_array($slug, $invalidSlugs); $i++)
            $slug = $defaultSlug.$this->separator.$i;

        return $slug;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $currentSlug = $this->getPropertyValue($entity, $property);
        $slug = $this->getSlug($entity, $property, $currentSlug);
        $this->setPropertyValue($entity, $property, $slug);
    }

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $uow = $event->getEntityManager()->getUnitOfWork();

        $classMetadata = $this->getClassMetadata(get_class($entity));
        $invalidSlugs = $this->getInvalidSlugs($event, $entity, $property);

        $currentSlug = $this->getPropertyValue($entity, $property);
        $slug = $this->getSlug($entity, $property, $currentSlug, $invalidSlugs);
        $this->setPropertyValue($entity, $property, $slug);

        $uow->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }
}
