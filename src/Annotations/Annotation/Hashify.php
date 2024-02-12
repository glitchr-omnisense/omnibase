<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;

use Base\Database\Mapping\ClassMetadataManipulator;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Class Hashify
 * package Base\Annotations\Annotation\Hashify
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY"})
 */

 #[\Attribute(\Attribute::TARGET_PROPERTY)]
class Hashify extends AbstractAnnotation
{
    public ?string $referenceColumn;
    public bool $random;
    public string $algorithm;
    public string $hashAlgorithm;
    public string $plain;
    public bool $nullable;
    public array $migrateFrom;
    public int $keyLength;
    public bool $ignoreCase;
    public bool $encodeAsBase64;
    public int $iterations;
    public ?int $cost;
    public ?int $memoryCost;
    public ?int $timeCost;

    public function __construct(
        ?string $reference = null, 
        bool $random = false, 
        string $algorithm = "auto",
        string $hash_algorithm = "sha512", 
        bool $nullable = false,
        array $migrateFrom = [],
        int $key_length = 40,
        bool $ignore_case = false,
        bool $encore_as_base64 = true,
        int $iterations = 5000,
        ?int $cost = null,
        ?int $memory_cost = null,
        ?int $time_cost = null
    )
    {
        $this->random = $random;
        $this->referenceColumn = $reference;
        $this->algorithm = $algorithm;
        $this->hashAlgorithm = $hash_algorithm;
        $this->nullable = $nullable;
        $this->migrateFrom = $migrateFrom;
        $this->keyLength = $key_length;
        $this->ignoreCase = $ignore_case;
        $this->encodeAsBase64 = $encore_as_base64;
        $this->iterations = $iterations;
        $this->cost = $cost;
        $this->memoryCost = $memory_cost;
        $this->timeCost = $time_cost;
    }

    /**
     * @param $className
     * @param $property
     * @return mixed|null
     * @throws Exception
     */
    public static function getHashify($className, $property)
    {
        $annotations = AnnotationReader::getInstance()->getPropertyAnnotations($className, Hashify::class);
        $that = $annotations[$property] ?? [];
        $that = array_pop($that);

        return ($that ?: null);
    }

    /**
     * @param $entity
     * @return PasswordHasherInterface|null
     */
    public function getMessageHasher($entity)
    {
        $hasherFactory = new PasswordHasherFactory([ClassUtils::getClass($entity) => [
            "algorithm" => $this->algorithm,
            "hash_algorithm" => $this->hashAlgorithm,
            "migrate_from" => $this->migrateFrom,
            "key_length" => $this->keyLength,
            "ignore_case" => $this->ignoreCase,
            "encode_as_base64" => $this->encodeAsBase64,
            "iterations" => $this->iterations,
            "cost" => $this->cost,
            "memory_cost" => $this->memoryCost,
            "time_cost" => $this->timeCost
        ]]);

        return $hasherFactory->getPasswordHasher(ClassUtils::getClass($entity)) ?? null;
    }

    /**
     * @param $entity
     * @param string|null $property
     * @return string|null
     * @throws Exception
     */
    private function getHashedMessage($entity, ?string $property = null): ?string
    {
        $plainMessage = $this->getPlainMessage($entity) ?? null;
        if ($plainMessage) {
            return $this->getMessageHasher($entity)->hash($plainMessage);
        }

        return ($property ? $this->getFieldValue($entity, $property) : null);
    }

    /**
     * @param $entity
     * @param string $hashedMessage
     * @return bool
     */
    public function needsRehash($entity, string $hashedMessage): bool
    {
        return $this->getMessageHasher($entity)->needsRehash($hashedMessage);
    }

    /**
     * @param $entity
     * @param $property
     * @param $hashedMessage
     * @return bool
     * @throws Exception
     */
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

    /**
     * @param $entity
     * @return string|null
     * @throws Exception
     */
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

    /**
     * @param $entity
     * @return ClassMetadataManipulator
     * @throws Exception
     */
    private function erasePlainMessage($entity)
    {
        if (!$this->referenceColumn) {
            throw new Exception("Attribute \"reference\" missing for @Hashify in " . ClassUtils::getClass($entity));
        }

        return $this->setPropertyValue($entity, $this->referenceColumn, ($this->nullable ? null : ""));
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     */
    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $value = $this->getHashedMessage($entity);
        if ($value) {
            $this->setFieldValue($entity, $property, $value);
        }
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     */
    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $value = $this->getHashedMessage($entity);
        if ($value) {
            $this->setFieldValue($entity, $property, $value);
        }
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     * @throws Exception
     */
    public function postPersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $this->erasePlainMessage($entity);
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     * @throws Exception
     */
    public function postUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $this->erasePlainMessage($entity);
    }
}
