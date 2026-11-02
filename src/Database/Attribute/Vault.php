<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Traits\VaultTrait;
use Base\Database\Attribute\Backend\VaultBackend;
use Doctrine\ORM\Mapping\ClassMetadata;
use Omnibridge\Persistence\Lifecycle;
use Base\Exception\VaultKeyNotFoundException;
use Exception;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;
use Symfony\Component\PropertyAccess\PropertyAccess;

use function is_file;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Vault extends AbstractAttribute
{
    // Its mapping on Doctrine (its unique constraints) is omnibridge/doctrine's DoctrineMappingHooks::vault() since the
    // plan "omnibase agnostic"'s step F3: it was loadClassMetadata(), a hook typed on Doctrine's ClassMetadata.

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

        return ($target == AttributeReader::TARGET_CLASS);
    }

    /**
     * Where the key pair of a vault is: Symfony's own secrets vault of that
     * environment (`bin/console secrets:generate-keys`).
     */
    public function getKeyPath(?string $vault = null): string
    {
        $vault ??= $this->getEnvironment();

        return $this->getProjectDir() . "/config/secrets/{$vault}/{$vault}.decrypt.private.php";
    }

    /**
     * The key pair of a vault: config/secrets/<vault>/<vault>.decrypt.private.php,
     * else - for the running environment - the SYMFONY_DECRYPTION_SECRET
     * variable (the same key, base64-encoded: how a production server is
     * given it without the file).
     */
    public function loadKeys(?string $vault = null): array
    {
        $vault ??= $this->getEnvironment();

        $path = $this->getKeyPath($vault);
        if (is_file($path)) {
            $keypair = include $path;
        } elseif ($vault === $this->getEnvironment() && ($secret = $_SERVER['SYMFONY_DECRYPTION_SECRET'] ?? $_ENV['SYMFONY_DECRYPTION_SECRET'] ?? null)) {
            $keypair = base64_decode((string) $secret, true);
        } else {
            throw new VaultKeyNotFoundException($vault, $path);
        }

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
     * base.vault.allow_plaintext: the behaviour of before - without a key
     * pair, a secured field is stored as it is. Off unless a site turns it on.
     */
    public function allowsPlaintext(): bool
    {
        try {
            // AbstractAttribute::getParameterBag() takes no key: it returns the bag, which
            // (bool) read as true - every secured field went in clear without a key pair.
            // Strictly true, so that anything else than the site's own "true" fails closed.
            return true === $this->getParameterBag()->get('base.vault.allow_plaintext');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether a secured field can be stored in this vault: its key pair is
     * there, or the site accepted clear text.
     */
    public function canSeal(?string $vault = null): bool
    {
        return null !== $this->getMarshaller($vault) || $this->allowsPlaintext();
    }

    /**
     * Fails closed: without the vault's key pair the value is refused
     * (VaultKeyNotFoundException), never stored in clear - unless the site
     * said so (base.vault.allow_plaintext).
     *
     * @param MarshallerInterface|null $marshaller
     * @param string|null $value
     * @throws VaultKeyNotFoundException
     */
    public function seal(?MarshallerInterface $marshaller, mixed $value, ?string $vault = null): string
    {
        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }

        if ($marshaller === null) {
            if ($this->allowsPlaintext()) {
                return (string) $value;
            }

            throw new VaultKeyNotFoundException($vault ?? $this->getEnvironment(), $this->getKeyPath($vault));
        }

        $failed = [];
        $values = $marshaller->marshall([$value], $failed);
        if ($failed || !isset($values[0])) {
            if ($this->allowsPlaintext()) {
                return (string) $value;
            }

            throw new VaultKeyNotFoundException($vault ?? $this->getEnvironment(), $this->getKeyPath($vault), 'the value could not be sealed');
        }

        return (string) base64_encode($values[0]);
    }

    /**
     * @param MarshallerInterface|null $marshaller
     * @param string|null $value
     * @return mixed|null
     */
    public function reveal(?MarshallerInterface $marshaller, ?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        try { $value = $marshaller?->unmarshall(base64_decode($value)) ?? $value; }
        catch (Exception $e) { }

        // seal() serialize()s arrays/objects, so reveal() genuinely returns
        // whatever was sealed — a string, or the unserialized array/object.
        // The declared type was `?string` (contradicting this method's own
        // `@return mixed|null` docblock); with the previously-broken
        // is_serialized() that mismatch was masked because nothing was ever
        // unserialized. Now that is_serialized() works, the type must be mixed
        // or an array/object @Vault field would hit "Array to string conversion".
        return is_serialized($value) ? unserialize($value) : $value;
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): a secured field given a new plain value is
     * written again, through the persistence's VaultBackend (the field holds the sealed value it read
     * when the plain one equals it, so the persistence would see no change to write).
     */
    public function onPreFlush(Lifecycle\PreFlush $event, object $entity, ?string $property = null): void
    {
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

                $this->backend($event, VaultBackend::class)->scheduleUpdate($entity);
            }
        }
    }

    /** The neutral hook (plan "omnibase agnostic", step F2b): the secured fields sealed before the update. */
    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $this->sealFields($event->entity);
    }

    /** The neutral hook (plan "omnibase agnostic", step F2b): the secured fields sealed before the insertion. */
    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $this->sealFields($event->entity);
    }

    /**
     * Each secured field of the entity sealed in its vault, the sealed and the plain value kept in its
     * vault bag (formerly preLifecycleEvent(), which took Doctrine's event and metadata it did not read).
     */
    public function sealFields(object $entity): void
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

                $sealedValue = $this->seal($marshaller, $plainValue, $vault);
                $propertyAccessor->setValue($entity, $field, $sealedValue);
                $entity->setVaultBag($field, $sealedValue, $plainValue);
            }
        }
    }

    /** The neutral hook (plan "omnibase agnostic", step F2b): the secured fields revealed after the flush. */
    public function onPostFlush(Lifecycle\PostFlush $event, object $entity, ?string $property = null): void
    {
        $this->revealFields($entity);
    }

    /** The neutral hook (plan "omnibase agnostic", step F2b): the secured fields revealed once read. */
    public function onPostLoad(Lifecycle\PostLoad $event, ?string $property = null): void
    {
        $this->revealFields($event->entity);
    }

    /**
     * Each sealed field of the entity revealed, the sealed and the plain value kept in its vault bag
     * (formerly postLifecycleEvent(), which took Doctrine's event and metadata it did not read).
     */
    public function revealFields(object $entity): void
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
