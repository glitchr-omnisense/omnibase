<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\Factory\EntityExtensionInterface;
use Base\Enum\EntityAction;

/**
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("field", type = "string"),
 * })
 */
class Trasheable extends AbstractAnnotation implements EntityExtensionInterface
{
    public function __construct( array $data = [])
    {
    }

    public function supports(string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_CLASS);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public static $trackedColumns   = [];
    public static function get():array { return self::$trackedColumns; }
    public static function has($entity, $property):bool { return isset(self::$trackedColumns[$entity]) && in_array($property, self::$trackedColumns[$entity]); } 
    
    public function payload(string $action, string $className, array $properties, object $entity): array
    {
        switch($action) {

            case EntityAction::INSERT:
            case EntityAction::UPDATE:

                // $trashBall = $trashBall ?? new TrashBall();
                break;

            case EntityAction::DELETE:
                break;

            default:
                throw new \Exception("Unknown action \"$action\" passed to ".__CLASS__);
        }

        return [];
    }

    // public function preFlush(PreFlushEventArgs $event) {

    //     $em = $event->getEntityManager();
    //     foreach ($em->getUnitOfWork()->getScheduledEntityDeletions() as $object) {
    //         if (method_exists($object, "getDeletedAt")) {
    //             if ($object->getDeletedAt() instanceof \Datetime) {
    //                 continue;
    //             } else {
    //                 $object->setDeletedAt(new \DateTime());
    //                 $em->merge($object);
    //                 $em->persist($object);
    //             }
    //         }
    //     }
    // }
}
