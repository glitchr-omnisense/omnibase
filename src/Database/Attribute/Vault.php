<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAnnotation;
use Base\Attributes\AnnotationReader;
use Base\Database\Traits\VaultTrait;
use Base\Database\Entity\Extension\TranslationInterface;
use Base\Database\Walker\TranslatableWalker;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;
use Symfony\Component\PropertyAccess\PropertyAccess;


use function is_file;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Vault extends AbstractAnnotation
{
    /**
     * @var string
     */
    public string $vault;

    /**
     * @var array
     */
    public array $fields;

    /**
     * @var array
     */
    public array $unique;

    public function __construct(string $vault = "vault", array $fields = [], array $unique = [])
    {
        $this->vault = $vault;
        $this->fields = $fields;
        $this->unique = $unique;
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
            if (!$this->vault) {
                throw new Exception("Vault field for environment context missing, please provide a valid field \"" . $this->vault . "\"");
            }

            if (!$object->getFieldName($this->vault)) {
                throw new Exception("Field \"" . $this->vault . "\" is missing, did you forget to import \"" . VaultTrait::class . "\" ?");
            }
        }

        return ($target == AnnotationReader::TARGET_CLASS);
    }

    public function loadKeys(?string $vault = null): array
    {
        $vault ??= $this->getEnvironment();

        $path = $this->getProjectDir()
            . "/config/secrets/{$vault}/{$vault}.decrypt.private.php";

        if (!is_file($path)) {
            throw new Exception("Vault keypair not found");
        }

        $keypair = include $path;

        if (!is_string($keypair) ||
            strlen($keypair) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES) {
            throw new Exception('Invalid sodium keypair');
        }

        return [$keypair];
    }

    public function getMarshaller(?string $vault = null): ?MarshallerInterface
    {
        try {
            $keys = $this->loadKeys($vault);
        } catch (Exception $e) {
            return null;
        }

        return new SodiumMarshaller($keys);
    }

    /**
     * @param MarshallerInterface|null $marshaller
     * @param string|null $value
     * @return array|mixed|null
     */
    public function seal(?MarshallerInterface $marshaller, mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }

        // no vault keys available: store the value unsealed
        if ($marshaller === null) {
            return (string) $value;
        }

        $failed = [];
        $values = $marshaller->marshall([$value], $failed);
        if ($failed) {
            return $value;
        }

        return (string) base64_encode($values[0]);
    }

    /**
     * @param MarshallerInterface|null $marshaller
     * @param string|null $value
     * @return mixed|null
     */
    public function reveal(?MarshallerInterface $marshaller, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try { $value = $marshaller?->unmarshall(base64_decode($value)) ?? $value; }
        catch (Exception $e) { }

        return is_serialized($value) ? unserialize($value) : $value;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        if ($classMetadata->reflClass === null) {
            return;
        } // Class has not yet been fully built, ignore this event

        if ($classMetadata->isMappedSuperclass) {
            return;
        }

        $namingStrategy = $this->getEntityManager()->getConfiguration()->getNamingStrategy();
        if ($this->unique) {
            $name = $namingStrategy->classToTableName($classMetadata->name) . '_unique';
            $classMetadata->table['uniqueConstraints'][$name]["columns"] = array_unique(array_merge(
                $classMetadata->table['uniqueConstraints'][$name]["columns"] ?? [],
                $this->unique
            ));
        }

        if (is_instanceof($classMetadata->name, TranslationInterface::class)) {
            $name = $namingStrategy->classToTableName($classMetadata->rootEntityName) . '_' . TranslatableWalker::SALT;
            if ($classMetadata->getName() == $classMetadata->rootEntityName) {
                $classMetadata->table['uniqueConstraints'][$name] ??= [];
                $classMetadata->table['uniqueConstraints'][$name]["columns"] = array_unique(array_merge(
                    $classMetadata->table['uniqueConstraints'][$name]["columns"] ?? [],
                    [$this->vault]
                ));
            }
        }
    }

    public function preFlush(PreFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
        $vault = $entity->getVault();

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($this->fields as $field) {
            if (!$entity->isSecured()) {
                continue;
            }

            if ($propertyAccessor->isReadable($entity, $field)) {
                $value = $propertyAccessor->getValue($entity, $field);
                if ($value === null) {
                    continue;
                }

                if ($entity->getSealedVaultBag($field) == $value) {
                    continue;
                }
                if ($entity->getPlainVaultBag($field) == $value) {
                    $propertyAccessor->setValue($entity, $field, $entity->getSealedVaultBag($field));
                    continue;
                }

                $this->getEntityManager()->getUnitOfWork()->scheduleForUpdate($entity);
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
        $this->preLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
        $this->preLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    /**
     * @param $event
     * @param ClassMetadata $classMetadata
     * @param mixed $entity
     * @param string|null $property
     * @return void
     */
    public function preLifecycleEvent($event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
        $vault = $entity->getVault();
        $marshaller = $this->getMarshaller($vault);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($this->fields as $field) {

            if (!$entity->isSecured()) {
                continue;
            }

            if ($propertyAccessor->isReadable($entity, $field)) {

                $plainValue = $propertyAccessor->getValue($entity, $field);
                if ($plainValue === null) {
                    continue;
                }

                $sealedValue = $this->seal($marshaller, $plainValue);
                $propertyAccessor->setValue($entity, $field, $sealedValue);
                $entity->setVaultBag($field, $sealedValue, $plainValue);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->postLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->postLifecycleEvent($event, $classMetadata, $entity, $property);
    }

    /**
     * @param $event
     * @param ClassMetadata $classMetadata
     * @param mixed $entity
     * @param string|null $property
     * @return void
     */
    public function postLifecycleEvent($event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $vault = $entity->getVault();
        $marshaller = $this->getMarshaller($vault);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($this->fields as $field) {

            if (!$entity->isSecured()) {
                continue;
            }

            if ($propertyAccessor->isReadable($entity, $field)) {

                $sealedValue = $propertyAccessor->getValue($entity, $field);
                if (!is_string($sealedValue) || empty($sealedValue)) {
                    $sealedValue = null;
                }

                if (is_string($sealedValue)) {

                    $plainValue = $this->reveal($marshaller, $sealedValue);
                    $propertyAccessor->setValue($entity, $field, $plainValue);
                    $entity->setVaultBag($field, $sealedValue, $plainValue);
                }
            }
        }
    }
}
