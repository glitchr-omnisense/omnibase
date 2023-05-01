<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Database\Traits\TrasheableTrait;
use Base\Entity\Extension\TrashBall;
use Base\Enum\EntityAction;
use DateTime;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

/**
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("field", type = "string"),
 * })
 */
class Trasheable extends AbstractAnnotation implements EntityExtensionInterface
{
    public $deletedAt;

    public function __construct(array $data = [])
    {
        $this->deletedAt = $data["field"] ?? "deletedAt";
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if ($object instanceof ClassMetadata) {
            if (!$this->deletedAt) {
                throw new Exception("Timing field for deletion missing, please provide a valid field \"" . $this->deletedAt . "\"");
            }

            if (!$classMetadata->getFieldName($this->deletedAt)) {
                throw new Exception("Field \"".$this->deletedAt."\" is missing, did you forget to import \"".TrasheableTrait::class."\" ?");
            }
        }

        return ($target == AnnotationReader::TARGET_CLASS);
    }

    public static $trackedEntities = []; // @TODO TO BE IMPLEMENTED

    public static function get(): array
    {
        return self::$trackedEntities;
    }

    public static function has(string $className, ?string $property = null): bool
    {
        return array_key_exists($className, self::$trackedEntities);
    }

    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        switch ($action) {
            case EntityAction::INSERT:
            case EntityAction::UPDATE:

                $trashBall = $trashBall ?? new TrashBall();
                break;

            case EntityAction::DELETE:

                if (!$entity->getDeletedAt() instanceof Datetime) {
                    $entity->setDeletedAt(new DateTime());
                    $this->getEntityManager()->merge($entity);
                    $this->getEntityManager()->persist($entity);
                }

                break;

            default:
                throw new Exception("Unknown action \"$action\" passed to " . __CLASS__);
        }

        return [];
    }
}
