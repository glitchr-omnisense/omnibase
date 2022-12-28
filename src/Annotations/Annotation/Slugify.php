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
 *   @Attribute("sync",      type = "bool"),
 *   @Attribute("unique",    type = "bool"),
 *   @Attribute("nullable",  type = "bool"),

 *   @Attribute("locale",    type = "string"),
 *   @Attribute("map",       type = "array"),
 *   @Attribute("separator", type = "string"),
 *   @Attribute("keep",      type = "array"),
 *   @Attribute("lowercase", type = "bool")
 * })
 */
class Slugify extends AbstractAnnotation
{
    protected $slugger;
    protected bool $unique;
    protected bool $lowercase;
    protected bool $nullable;

    protected ?array $keep;
    protected bool $sync;

    protected string $separator;
    protected ?string $referenceColumn;

    public function __construct( array $data )
    {
        $this->referenceColumn = $data['reference'] ?? null;

        $this->unique    = $data['unique']   ?? true;
        $this->sync      = $data['sync']     ?? false;
        $this->nullable  = $data["nullable"] ?? false;

        $this->separator = $data['separator'] ?? '-';
        $this->keep = $data['keep'] ?? null;
        $this->lowercase = $data['lowercase'] ?? true;
        $this->slugger   = new AsciiSlugger(
            $data["locale"] ?? null,
            $data["map"]    ?? null
        );
    }

    public function getReferenceColumn() { return $this->referenceColumn; }
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
            if($propertyDeclarer != $propertyDeclarer2 && !is_instanceof($propertyDeclarer, $propertyDeclarer2)) continue;

            $invalidSlugs[] = $this->getFieldValue($entity2, $property);
        }

        $firstEntity = begin($candidateEntities);
        if($firstEntity === $entity) {
            $firstSlug = $this->getFieldValue($entity, $property);
            $invalidSlugs = array_filter($invalidSlugs, fn($s) => $s !== $firstSlug);
        }

        return $invalidSlugs;
    }

    public function slug($entity, ?string $input = null, string $suffix = ""): ?string
    {
        // Check if field already set.. get field value or by default class name
        if(!$input && $this->referenceColumn) $input = $this->getPropertyValue($entity, $this->referenceColumn) ?? $this->getFieldValue($entity, $this->referenceColumn);
        if(!$input && $this->nullable) return null;

        if(!$input) $input = camel2snake(class_basename($entity), "-");
        $input .= !empty($suffix) ? $this->separator.$suffix : "";

        if(!$this->keep) $slug = $this->slugger->slug($input, $this->separator);
        else {

            $pos = 0;
            $posList = [];

            $pos = -1;
            while( ($pos = strmultipos($input, $this->keep, $pos+1)) )
                $posList[] = $input[$pos];

            $slug = explodeByArray($this->keep, $input);
            $slug = array_map(fn($i) => $this->slugger->slug($i, $this->separator), $slug);
            $slug = implodeByArray($posList, $slug);
        }

        return ($this->lowercase ? strtolower($slug) : $slug);
    }

    public function getSlug($entity, string $property, ?string $defaultInput = null, array &$invalidSlugs = []): ?string
    {
        /**
         * @var ServiceRepositoryInterface
         */
        $repository  = $this->getPropertyOwnerRepository($entity, $property);
        $defaultSlug = $this->slug($entity, $defaultInput);
        $slug = $defaultSlug;
        if(!$slug) return null;

        if(!$this->unique) return $slug;
        for($i = 2; $repository->findOneBy([$property => $slug]) || in_array($slug, $invalidSlugs); $i++)
            $slug = $defaultSlug.$this->separator.$i;

        return $slug;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $propertyDeclarer  = property_declarer($entity , $property);
        $classMetadata = $this->getClassMetadata($propertyDeclarer);
        $invalidSlugs = $this->getInvalidSlugs($event, $entity, $property);

        if($this->sync) {

            $slug = $this->getFieldValue($entity, $property);

            $oldEntity = $this->getOldEntity($entity);
            $oldSlug   = $this->getFieldValue($oldEntity, $property);

            if ($slug == $oldSlug) {

                $labelModified = !$this->referenceColumn ? null :
                    $this->getPropertyValue($oldEntity, $this->referenceColumn) !== $this->getPropertyValue($entity, $this->referenceColumn);

                if($labelModified)
                    $slug = $this->getSlug($entity, $property);
            }

        } else {

            $currentSlug = $this->getFieldValue($entity, $property);
            $slug = $this->getSlug($entity, $property, $currentSlug, $invalidSlugs);
        }

        $this->setFieldValue($entity, $property, $slug);

        if ($this->getUnitOfWork()->getEntityChangeSet($entity))
            $this->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }
}
