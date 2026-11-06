<?php

namespace Base\Database\Attribute;

use Omnibridge\Persistence\Lifecycle;
use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;
use Exception;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;


/**
 * Class Hashify
 * package Base\Database\Attribute\Hashify
 */

 #[\Attribute(\Attribute::TARGET_PROPERTY)]
class Hashify extends AbstractAttribute implements ExtensionOptionInterface
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
        $attributes = AttributeReader::getInstance()->getPropertyAttributes($className, Hashify::class);
        $that = $attributes[$property] ?? [];
        $that = array_pop($that);

        return ($that ?: null);
    }

    /**
     * @param $entity
     * @return PasswordHasherInterface|null
     */
    public function getMessageHasher($entity)
    {
        $hasherFactory = new PasswordHasherFactory([self::className($entity) => [
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

        return $hasherFactory->getPasswordHasher(self::className($entity)) ?? null;
    }

    /**
     * @param $entity
     * @param string|null $property
     * @return string|null
     * @throws Exception
     */
    private function getHashedMessage($entity, ?string $property = null, ?UnitOfWork $unitOfWork = null): ?string
    {
        $plainMessage = $this->getPlainMessage($entity, $unitOfWork) ?? null;
        if ($plainMessage) {
            return $this->getMessageHasher($entity)->hash($plainMessage);
        }

        return ($property ? $this->getFieldValue($entity, $property, $unitOfWork) : null);
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
        $className = self::className($entity);
        $attributes = AttributeReader::getInstance()->getPropertyAttributes($className, Hashify::class);
        $that = $attributes[$property] ?? [];

        if (!($that = array_pop($that))) {
            throw new Exception("@Hashify attribute not found in \"$property\" for $className");
        }

        if ($that->needsRehash($entity, $hashedMessage)) {
            throw new Exception("Password in @Hashify attribute in \"$property\" for $className needs to be rehashed");
        }

        // The values read on the host's unit of work's shapes (plan "omnibase agnostic", step F7d).
        $unitOfWork = Runtime::find(UnitOfWork::class);

        return $that->getMessageHasher($entity)->verify(
            $that->getHashedMessage($entity, $property, $unitOfWork),
            $that->getFieldValue($entity, $property, $unitOfWork)
        );
    }

    /**
     * @param $entity
     * @return string|null
     * @throws Exception
     */
    private function getPlainMessage($entity, ?UnitOfWork $unitOfWork = null): ?string
    {
        if ($this->random) {
            return random_bytes(10);
        }

        if (!$this->referenceColumn) {
            throw new Exception("Attribute \"reference\" missing for @Hashify in " . self::className($entity));
        }

        return $this->getPropertyValue($entity, $this->referenceColumn, $unitOfWork);
    }

    /**
     * @param $entity
     * @return mixed
     * @throws Exception
     */
    private function erasePlainMessage($entity, ?UnitOfWork $unitOfWork = null)
    {
        if (!$this->referenceColumn) {
            throw new Exception("Attribute \"reference\" missing for @Hashify in " . self::className($entity));
        }

        return $this->setPropertyValue($entity, $this->referenceColumn, ($this->nullable ? null : ""), $unitOfWork);
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AttributeReader::TARGET_PROPERTY);
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     */
    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $entity = $event->entity;
        $value = $this->getHashedMessage($entity, null, $event->unitOfWork);
        if ($value) {
            $this->setFieldValue($entity, $property, $value, $event->unitOfWork);
        }
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     */
    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $entity = $event->entity;
        $value = $this->getHashedMessage($entity, null, $event->unitOfWork);
        if ($value) {
            $this->setFieldValue($entity, $property, $value, $event->unitOfWork);
        }
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     * @return void
     * @throws Exception
     */
    public function onPostPersist(Lifecycle\PostPersist $event, ?string $property = null): void
    {
        $entity = $event->entity;
        $this->erasePlainMessage($entity, $event->unitOfWork);
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     * @return void
     * @throws Exception
     */
    public function onPostUpdate(Lifecycle\PostUpdate $event, ?string $property = null): void
    {
        $entity = $event->entity;
        $this->erasePlainMessage($entity, $event->unitOfWork);
    }

    /**
     * The entity's class, a proxy seen through: the unit of work's className() when a persistence is installed,
     * else the class with Doctrine's proxy marker taken off (doctrine/common's ClassUtils::getClass() did this,
     * and the core does not require doctrine/common since 5.0).
     */
    private static function className(object $entity): string
    {
        $unitOfWork = Runtime::find(UnitOfWork::class);
        if ($unitOfWork instanceof UnitOfWork) {
            return $unitOfWork->className($entity);
        }

        $class = $entity::class;

        return false === ($marker = strrpos($class, '\\__CG__\\')) ? $class : substr($class, $marker + 8);
    }
}
