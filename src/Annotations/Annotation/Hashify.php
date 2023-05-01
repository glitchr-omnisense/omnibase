<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class Hashify
 * package Base\Annotations\Annotation\Hashify
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("reference",        type = "string"),
 *   @Attribute("random",           type = "bool"),
 *   @Attribute("algorithm",        type = "string"),
 *   @Attribute("hash_algorithm",   type = "string"),
 *   @Attribute("migrate_from",     type = "array"),
 *   @Attribute("key_length",       type = "integer"),
 *   @Attribute("ignore_case",      type = "bool"),
 *   @Attribute("encode_as_base64", type = "bool"),
 *   @Attribute("iterations",       type = "integer"),
 *   @Attribute("cost",             type = "int"),
 *   @Attribute("memory_cost",      type = "int"),
 *   @Attribute("time_cost",        type = "int"),
 *
 *   @Attribute("plain", type = "string"),
 *   @Attribute("nullable", type = "bool")
 * })
 */
class Hashify extends AbstractAnnotation
{
    protected array $data;
    protected mixed $nullable;
    protected $reference;

    public bool $random;
    public ?string $referenceColumn;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->nullable = $data["nullable"] ?? false;
        $this->random = $data["random"] ?? false;
        $this->referenceColumn = $data["reference"] ?? null;
    }

    public static function getHashify($className, $property)
    {
        $annotations = AnnotationReader::getInstance()->getPropertyAnnotations($className, Hashify::class);
        $that = $annotations[$property] ?? [];
        $that = array_pop($that);

        return ($that ?: null);
    }

    public function getMessageHasher($entity)
    {
        $hasherFactory = new PasswordHasherFactory([ClassUtils::getClass($entity) => [
            "algorithm" => $this->data["algorithm"] ?? "auto",
            "hash_algorithm" => $this->data["hash_algorithm"] ?? "sha512",
            "migrate_from" => $this->data["migrate_from"] ?? [],
            "key_length" => $this->data["key_length"] ?? 40,
            "ignore_case" => $this->data["ignore_case"] ?? false,
            "encode_as_base64" => $this->data["encode_as_base64"] ?? true,
            "iterations" => $this->data["iterations"] ?? 5000,
            "cost" => $this->data["cost"] ?? null,
            "memory_cost" => $this->data["memory_cost"] ?? null,
            "time_cost" => $this->data["time_cost"] ?? null
        ]]);

        return $hasherFactory->getPasswordHasher(ClassUtils::getClass($entity)) ?? null;
    }

    private function getHashedMessage($entity, ?string $property = null): ?string
    {
        $plainMessage = $this->getPlainMessage($entity) ?? null;
        if ($plainMessage) {
            return $this->getMessageHasher($entity)->hash($plainMessage);
        }

        return ($property ? $this->getFieldValue($entity, $property) : null);
    }

    public function needsRehash($entity, string $hashedMessage): bool
    {
        return $this->getMessageHasher($entity)->needsRehash($hashedMessage);
    }

    public static function isValid($entity, $property, $hashedMessage): bool
    {
        $className = ClassUtils::getClass($entity);
        $annotations = AnnotationReader::getInstance()->getPropertyAnnotations($className, Hashify::class);
        $that = $annotations[$property] ?? [];

        if (!($that = array_pop($that))) {
            throw new Exception("@Hashify annotation not found in \"$property\" for $className");
        }

        if ($that->needsRehash($entity, $hashedMessage)) {
            throw new Exception("Password in @Hashify annotation in \"$property\" for $className needs to be rehashed");
        }

        return $that->getMessageHasher($entity)->verify(
            $that->getHashedMessage($entity, $property),
            $that->getFieldValue($entity, $property)
        );
    }

    private function getPlainMessage($entity): ?string
    {
        if ($this->random) {
            return random_bytes(10);
        }

        if (!$this->referenceColumn) {
            throw new Exception("Attribute \"reference\" missing for @Hashify in " . ClassUtils::getClass($entity));
        }

        return $this->getPropertyValue($entity, $this->referenceColumn);
    }

    private function erasePlainMessage($entity)
    {
        if (!$this->referenceColumn) {
            throw new Exception("Attribute \"plain\" missing for @Hashify in " . ClassUtils::getClass($entity));
        }

        return $this->setPropertyValue($entity, $this->referenceColumn, ($this->nullable ? null : ""));
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $value = $this->getHashedMessage($entity);
        if ($value) {
            $this->setFieldValue($entity, $property, $value);
        }
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $value = $this->getHashedMessage($entity);
        if ($value) {
            $this->setFieldValue($entity, $property, $value);
        }
    }

    public function postPersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $this->erasePlainMessage($entity);
    }

    public function postUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $this->erasePlainMessage($entity);
    }
}
