<?php

namespace Base\Database\Annotation;

use App\Entity\Blog\Comment;
use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Class Slugify
 * package Base\Database\Annotation\Slugify
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("reference", type = "string"),
 *   @Attribute("updatable", type = "bool"),
 *   @Attribute("unique",    type = "bool"),
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
    protected string $reference;

    public function __construct( array $data ) {

        $this->referenceColumn = $data['reference'] ?? null;

        $this->updatable = $data['updatable'] ?? false;
        $this->unique    = $data['unique']    ?? true;

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

    public function getSlug($entity, ?string $defaultInput = null, string $suffix = ""): string
    {
        if (!$this->referenceColumn)
            throw new Exception("Attribute \"reference\" missing for @Slugify in " . get_class($entity));

        // Check if field already set.. (it needs to be checked)
        $input = $defaultInput ?? $this->getFieldValue($entity, $this->referenceColumn);
        $input = $input . (!empty($suffix) ? $this->separator.$suffix : "");

        $slug = $this->slugger->slug($input, $this->separator);
        return ($this->lowercase ? $slug->lower() : $slug);
    }

    public function getUniqueSlug($entity, string $property, ?string $defaultInput = null, array $invalidSlugs = []): string
    {
        $defaultSlug = $this->getSlug($entity, $defaultInput);

        $slug = $defaultSlug;
        $repository = $this->getEntityManager()->getRepository(get_class($entity));

        for($i = 1; $repository->findOneBy([$property => $slug]) || in_array($slug, $invalidSlugs); $i++)
            $slug = $defaultSlug.$this->separator.$i;

        return $slug;
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, $entity, ?string $property = null)
    {
        $defaultInput = $this->getFieldValue($entity, $property);
        $slug = $this->getSlug($entity, $defaultInput);
        $this->setFieldValue($entity, $property, $slug);
    }

    public function onFlush(OnFlushEventArgs $event, $entity, ?string $property = null)
    {
        $uow = $event->getEntityManager()->getUnitOfWork();

        $candidateEntities = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity2)
            $candidateEntities[] = $entity2;
        foreach ($uow->getScheduledEntityUpdates() as $entity2)
            $candidateEntities[] = $entity2;

        $classMetadata = $this->getClassMetadata($entity);
        $propertyClassOrigin = $classMetadata->getFieldMapping($property)["declared"] ?? null;

        $invalidSlugs = [];
        foreach ($candidateEntities as $entity2) {

            if($entity === $entity2) continue;
            if(!is_subclass_of($entity2, $propertyClassOrigin)) continue;

            $invalidSlugs[] = $this->getFieldValue($entity2, $property);
        }

        $defaultInput = $this->getFieldValue($entity, $property);
        $slug = $this->getUniqueSlug($entity, $property, $defaultInput, $invalidSlugs);
        $this->setFieldValue($entity, $property, $slug);

        $uow->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }
}
