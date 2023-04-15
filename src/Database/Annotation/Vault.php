<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\TranslationInterface;
use Base\Database\Walker\TranslatableWalker;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;
use Symfony\Component\PropertyAccess\PropertyAccess;

use function GuzzleHttp\Promise\queue;
use function is_file;

/**
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("vault", type = "array"),
 *   @Attribute("fields", type = "array"),
 *   @Attribute("unique", type = "array")
 * })
 */

class Vault extends AbstractAnnotation
{
    public string $vault;
    public array $fields;
    public array $unique;

    public function __construct(array $data = [])
    {
        $this->vault  = $data["vault"] ?? "vault";
        $this->fields = $data["fields"] ?? [];
        $this->unique = $data["unique"] ?? [];
    }

    public function supports(string $target, ?string $targetValue = null, $classMetadata = null): bool
    {
        if ($classMetadata instanceof ClassMetadata) {
            if (!$this->vault) {
                throw new Exception("Vault field for environment context missing, please provide a valid field \"".$this->vault."\"");
            }
            if (!$classMetadata->getFieldName($this->vault)) {
                throw new Exception("Field \"".$this->vault."\" is missing, did you forget to import \"".VaultTrait::class."\" ?");
            }
        }

        return ($target == AnnotationReader::TARGET_CLASS);
    }

    private function loadKeys(?string $vault = null): array
    {
        if ($vault === null) {
            $vault = $this->getEnvironment();
        }

        $pathPrefix = $this->getProjectDir()."/config/secrets/".$vault."/".$vault.".";
        $decryptionKey = is_file($pathPrefix.'decrypt.private.php') ? (string) include $pathPrefix.'decrypt.private.php' : null;

        if ($decryptionKey === null) {
            throw new Exception('Decryption key not found in "'.dirname($pathPrefix).'".');
        }
        /* Rotation keys ? Encryption key ? Probably not needed.. input very welcome here :o) */
        // if (is_file($pathPrefix.'encrypt.public.php')) {
        //     $encryptionKey = (string) include $pathPrefix.'encrypt.public.php';
        // } elseif ('' !== $decryptionKey) {
        //     $encryptionKey = sodium_crypto_box_publickey($decryptionKey);
        // } else {
        //     throw new \RuntimeException(sprintf('Encryption key not found in "%s".', \dirname($pathPrefix)));
        // }

        return [$decryptionKey];
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

    public function seal(?MarshallerInterface $marshaller, ?string $value)
    {
        try {
            $failed = [];
            $encryptedValues = $marshaller?->marshall([$value], $failed)[0] ?? [];
            if (count($failed)) {
                return null;
            }

            return $encryptedValues;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function reveal(?MarshallerInterface $marshaller, ?string $value)
    {
        if ($value === null) {
            return null;
        }

        try {
            return $marshaller?->unmarshall($value);
        } catch (\Exception $e) {
            return null;
        }
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

    public function preFlush(\Doctrine\ORM\Event\PreFlushEventArgs $args, \Doctrine\ORM\Mapping\ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $vault = $entity->getVault();
        $marshaller = $this->getMarshaller($vault);

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

                if ($entity->getSealedVaultBag($field) == $value) continue;
                if ($entity->getPlainVaultBag($field) == $value) {
                    $propertyAccessor->setValue($entity, $field, $entity->getSealedVaultBag($field));
                    continue;
                }

                $this->getEntityManager()->getUnitOfWork()->scheduleForUpdate($entity);
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->preLifecycleEvent($event, $classMetadata, $entity, $property);
    }
    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->preLifecycleEvent($event, $classMetadata, $entity, $property);
    }
    public function preLifecycleEvent($event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
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

                if (is_array($plainValue) || is_object($plainValue)) {
                    $plainValue = serialize($plainValue);
                }

                $sealedValue = base64_encode($this->seal($marshaller, $plainValue));
                $propertyAccessor->setValue($entity, $field, $sealedValue);
                $entity->setVaultBag($field, $sealedValue, $plainValue);
            }
        }
    }

    public function postFlush(\Doctrine\ORM\Event\PostFlushEventArgs $args, \Doctrine\ORM\Mapping\ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->postLifecycleEvent($args, $classMetadata, $entity, $property);
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $this->postLifecycleEvent($event, $classMetadata, $entity, $property);
    }
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
                $plainValue = $sealedValue ? base64_decode($propertyAccessor->getValue($entity, $field)) : false;

                if ($plainValue === false) {
                    $plainValue = null;
                }

                if (is_string($plainValue)) {

                    $plainValue = $this->reveal($marshaller, $plainValue);
                    if (is_serialized($plainValue))
                        $plainValue = unserialize($plainValue);

                    $propertyAccessor->setValue($entity, $field, $plainValue);
                    $entity->setVaultBag($field, $sealedValue, $plainValue);
                }
            }
        }
    }
}
