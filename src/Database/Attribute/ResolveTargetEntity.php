<?php

namespace Base\Database\Attribute;

/**
 * #[ResolveTargetEntity(App\Entity\User::class)] on an interface an association targets: the entity class the
 * association is resolved to by default, wherever the package that declares the interface is installed - what
 * DoctrineBundle's `resolve_target_entities` (`doctrine.orm.resolve_target_entities`) says for an application, written
 * once by the package, read by every persistence (omnibase 7.2):
 *
 *     #[ResolveTargetEntity('App\Entity\User')]
 *     interface MerchantInterface
 *     {
 *     }
 *
 * - Eloquent, and any description of the entities (Base\Database\Schema\TargetRule): an association toward the
 *   interface resolved to the class, unless the options' resolve_target_entities resolve it otherwise;
 * - Doctrine under Symfony: omnibridge/symfony adds the interface to DoctrineBundle's resolve_target_entities listener
 *   (Base\Discovery::targets() finds the interfaces of the discovered packages), unless the application's
 *   doctrine.yaml resolves it already.
 *
 * An application always resolves the interface its own way through those options; the attribute is the default.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ResolveTargetEntity
{
    /** @param string $class the entity class (an App\ name is the application's own, else the core's alias) */
    public function __construct(public readonly string $class)
    {
    }

    /** The class an interface declares, null when it declares none (or is no interface). */
    public static function of(string $interface): ?string
    {
        if (!interface_exists($interface)) {
            return null;
        }
        $attributes = (new \ReflectionClass($interface))->getAttributes(self::class);

        return [] === $attributes ? null : ltrim($attributes[0]->newInstance()->class, '\\');
    }
}
