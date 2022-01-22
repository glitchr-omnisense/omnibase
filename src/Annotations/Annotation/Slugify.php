<?php

namespace Base\Annotations\Annotation;

use App\Entity\Blog\Comment;
use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
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
 *   @Attribute("locale",    type = "string"),
 *   @Attribute("map",       type = "array"),
 *   @Attribute("separator", type = "string"),
 *   @Attribute("lowercase", type = "bool")
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

            if($entity === $entity2) continue;
            if(!is_a($entity, get_class($entity2))) continue;

            $invalidSlugs[] = $this->getFieldValue($entity2, $property);
        }

        $firstEntity = begin($candidateEntities);
        if($firstEntity === $entity) {
            $firstSlug = $this->getFieldValue($entity, $property);
            $invalidSlugs = array_filter($invalidSlugs, fn($s) => $s !== $firstSlug);
        }

        dump(get_class($entity), $property, $invalidSlugs);
        return $invalidSlugs;
    }
    
    public function slug($entity, ?string $input = null, string $suffix = ""): string
    {
        // Check if field already set.. get field value or by default class name
        $className = explode("\\", get_class($entity));

        if(!$input && $this->referenceColumn) 
            $input = $this->getFieldValue($entity, $this->referenceColumn);
        if(!$input) 
            $input = end($className);

        $input .= !empty($suffix) ? $this->separator.$suffix : "";

        $slug = $this->slugger->slug($input, $this->separator);
        return ($this->lowercase ? $slug->lower() : $slug);
    }
    
    public function getSlug($entity, string $property, ?string $defaultInput = null, array $invalidSlugs = []): string
    {
        $repository  = $this->getPropertyOwnerRepository($entity, $property);
        $defaultSlug = $this->slug($entity, $defaultInput);

        $slug = $defaultSlug;
        if(!$this->unique) return $slug;
        
        dump("-------");
        dump(get_class($entity), $property, $defaultInput);
        dump(get_class($repository));
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
        $defaultInput = $this->getFieldValue($entity, $property);
        $slug = $this->getSlug($entity, $property, $defaultInput);
        $this->setFieldValue($entity, $property, $slug);
    }

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $uow = $event->getEntityManager()->getUnitOfWork();

        $classMetadata = $this->getClassMetadata(get_class($entity));
        $invalidSlugs = $this->getInvalidSlugs($event, $entity, $property);

        $defaultInput = $this->getFieldValue($entity, $property);
        $slug = $this->getSlug($entity, $property, $defaultInput, $invalidSlugs);
        $this->setFieldValue($entity, $property, $slug);

        $uow->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }
}
