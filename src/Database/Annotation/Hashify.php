<?php

namespace Base\Database\Annotation;

use BaconQrCode\Encoder\Encoder;
use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;
use DateTime;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use ReflectionObject;

use Symfony\Component\PasswordHasher\PasswordEncoderInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\MessageDigestPasswordHasher;

/**
 * Class Hashify
 * package Base\Database\Annotation\Hashify
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
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
 *   @Attribute("salt", type = "string"),
 *   @Attribute("nullable", type = "bool")
 * })
 */
class Hashify extends AbstractAnnotation
{
    protected $reference;
    protected $nullable;

    public function __construct( array $data )
    {
        // Prepare messageHasher
        $hasherFactory  = new PasswordHasherFactory([__CLASS__ => [
            "algorithm"        => $data["algorithm"] ?? "auto",
            "hash_algorithm"   => $data["hash_algorithm"] ?? "sha512",
            "migrate_from"     => $data["migrate_from"] ?? [],
            "key_length"       => $data["key_length"] ?? 40,
            "ignore_case"      => $data["ignore_case"] ?? false,
            "encode_as_base64" => $data["encode_as_base64"] ?? true,
            "iterations"       => $data["iterations"] ?? 5000,
            "cost"             => $data["cost"] ?? null,
            "memory_cost"      => $data["memory_cost"] ?? null,
            "time_cost"        => $data["time_cost"] ?? null
        ]]);

        $this->messageHasher = $hasherFactory->getPasswordHasher(__CLASS__);
        $this->nullable      = $data["nullable"] ?? false;
        $this->saltColumn     = $data["salt"] ?? null;
        $this->referenceColumn     = $data["reference"] ?? null;
    }

    private function getSalt($entity): ?string
    {
        if (!$this->saltColumn)
            return null;

        if ($this->hasField($entity, $this->saltColumn))
            return $this->getFieldValue($entity, $this->saltColumn);
    }

    private function getPlainMessage($entity): ?string
    {
        if (!$this->referenceColumn)
            throw new Exception("Attribute \"reference\" missing for @Hashify in " . get_class($entity));

        if ($this->hasField($entity, $this->referenceColumn))
            return $this->getFieldValue($entity, $this->referenceColumn);
    }

    private function erasePlainMessage($entity)
    {
        if (!$this->referenceColumn)
            throw new Exception("Attribute \"plain\" missing for @Hashify in " . get_class($entity));

        return $this->setFieldValue($entity, $this->referenceColumn, ($this->nullable ? null : ""));
    }

    private function getHashedMessage($entity, ?string $property = null): ?string
    {
        $salt         = $this->getSalt($entity)         ?? null;
        $plainMessage = $this->getPlainMessage($entity) ?? null;
        if($plainMessage)
            return $this->messageHasher->encodePassword($plainMessage, $salt);

        return ($property ? $this->getFieldValue($entity, $property) : null);
    }

    public static function getHashify($className, $property)
    {
        $annotations = AnnotationReader::getInstance()->getPropertyAnnotations($className, Hashify::class);
        $that = $annotations[$property] ?? [];
        $that = array_pop($that);

        return ($that ? $that : null);
    }

    public static function getMessageHasher($className, $property)
    {
        $annotations = AnnotationReader::getInstance()->getPropertyAnnotations($className, Hashify::class);
        $that = $annotations[$property] ?? [];
        $that = array_pop($that);

        return ($that ? $that->messageHasher : null);
    }

    public static function isPasswordValid($entity, $property, $value): bool
    {
        $className = get_class($entity);
        $annotations = AnnotationReader::getInstance()->getPropertyAnnotations($className, Hashify::class);
        $that = $annotations[$property] ?? [];

        if( !($that = array_pop($that)) )
            throw new Exception("@Hashify annotation not found in \"$property\" for $className");

        $hashedMessage = $that->getHashedMessage($entity, $property);

        return $that->messageHasher->isPasswordValid($hashedMessage, $value, $that->getSalt(null, $entity));
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, $entity, ?string $property = null)
    {
        $value = $this->getHashedMessage($entity);
        if($value) $this->setFieldValue($entity, $property, $value);
    }

    public function preUpdate(LifecycleEventArgs $event, $entity, ?string $property = null)
    {
        $value = $this->getHashedMessage($entity);
        if($value) $this->setFieldValue($entity, $property, $value);
    }

    public function postPersist(LifecycleEventArgs $event, $entity, ?string $property = null)
    {
        $this->erasePlainMessage($entity);
    }

    public function postUpdate(LifecycleEventArgs $event, $entity, ?string $property = null)
    {
        $this->erasePlainMessage($entity);
    }
}
