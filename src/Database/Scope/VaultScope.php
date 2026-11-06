<?php

namespace Base\Database\Scope;

use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Vault;
use Omnibridge\Persistence\Scope;

/**
 * The rows of another environment's vault left out of every read (plan
 * "omnibase agnostic", step F2e): an entity whose class is #[Vault] is read
 * when its vault field is null or names the environment the scope was enabled
 * with ("environment"). Doctrine compiles it as its SQL filter "vault_filter"
 * (Base\Database\Filter\VaultFilter).
 */
final class VaultScope implements Scope
{
    public const NAME = 'vault';

    public function name(): string
    {
        return self::NAME;
    }

    public function appliesTo(string $class): bool
    {
        return null !== self::vault($class);
    }

    /**
     * @param array{environment?: string} $parameters
     *
     * @return array<string, list<string|null>> the vault field => [null, the environment]: null or equal (a list
     *                                          holding null reads "null, or one of the others")
     */
    public function criteria(string $class, array $parameters = []): array
    {
        $vault = self::vault($class);
        if (null === $vault) {
            return [];
        }
        $environment = $parameters['environment'] ?? null;
        if (!\is_string($environment) || '' === $environment) {
            throw new \InvalidArgumentException(sprintf('No environment defined for the vault scope while reading %s.', $class));
        }

        return [$vault->vault => [null, $environment]];
    }

    /** The class's #[Vault], the last one declared. */
    public static function vault(string $class): ?Vault
    {
        $attributes = AttributeReader::classAttributesOf($class, Vault::class);

        return [] === $attributes ? null : end($attributes);
    }
}
