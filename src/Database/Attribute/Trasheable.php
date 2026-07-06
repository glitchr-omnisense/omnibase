<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Database\Entity\EntityExtension;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Database\Entity\Extension\TrasheableTrait;
use Base\Entity\Extension\TrashBall;
use Base\Enum\EntityAction;
use DateTime;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;


#[\Attribute(\Attribute::TARGET_CLASS)]
class Trasheable extends AbstractAttribute implements EntityExtensionInterface
{
    public string $deletedAt;
    public function __construct(string $field = "deletedAt")
    {
        $this->deletedAt = $field;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     * @throws Exception
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if ($object instanceof ClassMetadata) {
            if (!$this->deletedAt) {
                throw new Exception("Timing field for deletion missing, please provide a valid field \"" . $this->deletedAt . "\"");
            }

            if (!$object->getFieldName($this->deletedAt)) {
                throw new Exception("Field \"" . $this->deletedAt . "\" is missing, did you forget to import \"" . TrasheableTrait::class . "\" ?");
            }
        }

        return ($target == EntityExtension::TARGET_CLASS);
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
