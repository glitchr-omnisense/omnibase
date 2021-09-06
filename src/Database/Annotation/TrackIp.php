<?php

namespace Base\Database\Annotation;

use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;
use DateTime;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class TrackIp
 * package Base\Database\Annotation\TrackIp
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("on", type = "array"),
 * })
 */

class TrackIp extends AbstractAnnotation
{
    private array $context;

    /**
     * @var DateTime
     */
    private string $value;

    public function __construct( array $data ) {

        $this->context = array_map("strtolower", $data['on']);
    }

    public function getContext(): array {
        return $this->context;
    }

    public function getValue(): ?string {

        if(!$this->value) {

            $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
            foreach ($keys as $k) {

                if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) {
                    $this->value = $_SERVER[$k];
                    break;
                }
            }
        }

        return (filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) ? $this->value : null;
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null):bool
    {
        return in_array("update", $this->context) || in_array("create", $this->context);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if(!in_array("create", $this->context)) return;

        $this->setFieldValue($entity, $property, $this->getValue());
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array("update", $this->context)) return;

        $this->setFieldValue($entity, $property, $this->getValue());
    }
}
