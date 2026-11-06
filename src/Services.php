<?php

namespace Base;

use Omnibridge\Container\Declarations;

/**
 * The core's services as data (plan "omnibase agnostic", steps F5a and F5e slice 4; Marco's
 * answer 3: the services stay available everywhere): one declaration that Symfony's container,
 * Laravel's and omnisense/omnibridge's plain-PHP Omnibridge\Container read alike, so that no
 * service is wired three times. Every service of the core is here - what omnibridge/symfony's
 * config/omnibase/services/*.php registered until slice 4 - with its tags and its decoration.
 *
 * The definitions are in src/Resources/services/<group>.php, one file per group (the core's former
 * config/services/<group>.php), in the order of GROUPS. A host reads them all (definitions()) or a
 * group at a time (group(): omnibridge/symfony compiles each group in its place among its own
 * definitions, so that its container stays the one it was). Each definition:
 *
 * - `class` (the parent's when left out under a `parent`), `id` (the class by default), `parent`
 *   (another definition of the list, whose arguments it inherits);
 * - `requires`: the classes or interfaces a host must have for it (Twig, omniauth, glitchr/omnisign,
 *   glitchr/backup-manager...), `environment`: the one environment it is registered in (applies());
 * - `arguments` by position (0, 1, `index_N` over a parent's) or by name (`$name`), in
 *   Omnibridge\Container's syntax: '@id' a service, '@?id' a service or null where there is none
 *   ('@??id' and '@!id': Symfony's ignore-on-invalid and ignore-on-uninitialized), '%name%' a
 *   parameter, '@@' and '%%' escape them; `['!tagged_iterator' => tag]` the services of a tag,
 *   `['!service_closure' => '@id']` a closure returning one, `['!closure' => [callable]]` a callable
 *   as a Closure, `['!new' => ['class' => ..., 'arguments' => [...]]]` an object built in place,
 *   `['!iterator' => [...]]` a lazy list; '@.inner' the service a decoration wraps;
 * - `bind`: values by parameter name for what the arguments leave out;
 * - `factory`, `calls` (method, arguments), `decorates` (`id`, `inner`, `priority`, `on_invalid`),
 *   `tags` (a name, or `['name' => ..., attributes...]`), and Symfony's `public`, `shared`, `lazy`,
 *   `abstract`, `autowire`, `autoconfigure`;
 * - `contracts`: the aliases answering it - an interface or a former id, as a list item, or under its
 *   name with `public` and the alias it goes through (`target`);
 * - `symfony`: what only Symfony's container gives it, by its own services' ids (the router, the
 *   request stack, the cache pools, Twig's environment...): its `arguments` and `calls` replace the
 *   ones above there, its `parent`, `factory`, `bind` and `decorates` are added. Laravel's container and
 *   Omnibridge\Container autowire what the arguments above leave out (their contracts, by type).
 *
 * The ids a definition names that the list does not define are the host's: omnibridge's contracts
 * (Urls, Views, Environment, Translations...), Base\Service\ParameterBagInterface,
 * Base\Contract\MediaRouter, Base\Service\Storages, `imagine.bitmap`, `imagine.svg`,
 * `Symfony\Component\Translation\Translator` (docs/20-architecture/services.md). tests/ServicesTest
 * checks that Symfony's container answers the list.
 */
final class Services
{
    /** The groups, in the order a host reads them. */
    public const GROUPS = [
        'services', 'auth', 'collab', 'commons', 'core', 'database', 'demo', 'form', 'local-business', 'media',
        'redirections', 'twig', 'view', 'decoration',
    ];

    /** @var array<string, list<array<string, mixed>>> */
    private static array $groups = [];

    /**
     * Every definition of the core, the groups in their order.
     *
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        $definitions = [];
        foreach (self::GROUPS as $group) {
            array_push($definitions, ...self::group($group));
        }

        return $definitions;
    }

    /**
     * The definitions of a group, in their order.
     *
     * @return list<array<string, mixed>>
     */
    public static function group(string $group): array
    {
        if (!\in_array($group, self::GROUPS, true)) {
            throw new \InvalidArgumentException(sprintf('No group "%s" of services: %s.', $group, implode(', ', self::GROUPS)));
        }

        return self::$groups[$group] ??= require __DIR__.'/Resources/services/'.$group.'.php';
    }

    /**
     * Whether a host registers a definition: the classes and interfaces it requires are installed and, where it names
     * one, the host runs that environment (`demo`). omnisense/omnibridge's rule, which Omnibridge\Container and
     * omnibridge/laravel read the list with.
     *
     * @param array<string, mixed> $definition
     */
    public static function applies(array $definition, ?string $environment = null): bool
    {
        return Declarations::applies($definition, $environment);
    }

    /** @param array<string, mixed> $definition its `id`, its class by default */
    public static function id(array $definition): string
    {
        return Declarations::id($definition);
    }

    /**
     * A definition's contracts as alias => options (`public`, `target`): a list item is a private alias of the service.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<string, array{public?: bool, target?: string}>
     */
    public static function contracts(array $definition): array
    {
        return Declarations::contracts($definition);
    }

    /**
     * A definition's class, followed through its parents where it gives none (null under a parent the list does not define).
     *
     * @param array<string, mixed> $definition
     */
    public static function classOf(array $definition): ?string
    {
        $seen = [];
        while (!isset($definition['class'])) {
            $parent = $definition['parent'] ?? null;
            if (null === $parent || isset($seen[$parent]) || null === $definition = self::find($parent)) {
                return null;
            }
            $seen[$parent] = true;
        }

        return $definition['class'];
    }

    /** @return array<string, mixed>|null the definition of an id */
    public static function find(string $id): ?array
    {
        foreach (self::definitions() as $definition) {
            if (self::id($definition) === $id) {
                return $definition;
            }
        }

        return null;
    }
}
