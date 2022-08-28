<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("vault", type = "array"),
 *   @Attribute("fields", type = "array"),
 * })
 */
class Vault extends AbstractAnnotation
{
    public $fields;
    public function __construct( array $data = [])
    {
        $this->vault  = $data["vault"] ?? "vault";
        $this->fields = $data["fields"] ?? [];
    }

    public function supports(string $target, ?string $targetValue = null, $classMetadata = null): bool
    {
        if($classMetadata instanceof ClassMetadata) {

            if(!$this->vault) throw new Exception("Vault field for environment context missing, please provide a valid field \"".$this->vault."\"");
            if(!$classMetadata->getFieldName($this->vault)) throw new Exception("Field \"".$this->vault."\" is missing, did you forget to import \"".VaultTrait::class."\" ?");
        }

        return ($target == AnnotationReader::TARGET_CLASS);
    }

    private function loadKeys(?string $vault = null): array
    {
        if($vault === null) $vault = $this->getEnvironment();

        $pathPrefix = $this->getProjectDir()."/config/secrets/".$vault."/".$vault.".";
        $decryptionKey = is_file($pathPrefix.'decrypt.private.php') ? (string) include $pathPrefix.'decrypt.private.php' : null;

        if($decryptionKey === null) throw new Exception('Decryption key not found in "'.dirname($pathPrefix).'".');
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

    public function getMarshaller(?string $vault = null)
    {
        $keys = $this->loadKeys($vault);
        return new SodiumMarshaller($keys);
    }

    public function seal(MarshallerInterface $marshaller, ?string $value)
    {
        try {

            $failed = [];
            $encryptedValues = $marshaller->marshall([$value], $failed)[0] ?? [];
            if(count($failed)) return null;

            return $encryptedValues;

        } catch (\Exception $e) { return null; }
    }

    public function reveal(MarshallerInterface $marshaller, ?string $value)
    {
        if($value === null) return null;

        try { return $marshaller->unmarshall($value); }
        catch (\Exception $e) { return null; }
    }

    public function preUpdate  (LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null) { $this->preLifecycleEvent($event, $classMetadata, $entity, $property); }
    public function prePersist (LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null) { $this->preLifecycleEvent($event, $classMetadata, $entity, $property); }
    public function preLifecycleEvent(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $vault = $entity->getVault();
        $marshaller = $this->getMarshaller($vault);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach($this->fields as $field) {

            if(!$entity->isSecure()) continue;
            if($propertyAccessor->isReadable($entity, $field)) {

                $value = $propertyAccessor->getValue($entity, $field);
                if($value === null) continue;

                $propertyAccessor->setValue($entity, $field, base64_encode($this->seal($marshaller, $value)));
            }
        }
    }

    public function postUpdate (LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null) { $this->postLifecycleEvent($event, $classMetadata, $entity, $property); }
    public function postPersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null) { $this->postLifecycleEvent($event, $classMetadata, $entity, $property); }
    public function postLoad   (LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null) { $this->postLifecycleEvent($event, $classMetadata, $entity, $property); }
    public function postLifecycleEvent(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $vault = $entity->getVault();
        $marshaller = $this->getMarshaller($vault);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach($this->fields as $field) {

            if(!$entity->isSecure()) continue;
            if($propertyAccessor->isReadable($entity, $field)) {

                $value = $propertyAccessor->getValue($entity, $field);
                $value = $value ? base64_decode($propertyAccessor->getValue($entity, $field)) : false;
                if($value === false) $value = null;

                if(is_string($value))
                    $propertyAccessor->setValue($entity, $field, $this->reveal($marshaller, $value));
            }
        }
    }
}
