<?php

namespace Base\ApiPlatform\Metadata\Resource\Factory;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;

/**
 * Keeps one API resource per class the application actually uses.
 *
 * API Platform scans every bundle's Entity directory, so base-bundle's own
 * resources (Base\Entity\User, Base\Entity\Thread) are collected next to the
 * application's, and two things make the same resource show up twice:
 *
 * - An App\ override: App\Entity\User mirrors Base\Entity\User and extends it.
 *   Both declare the same operations under the same names.
 * - A class alias: where the application has no override, App\Entity\X is an
 *   alias of Base\Entity\X. PHP lists an alias among the declared classes -
 *   lowercased ("app\entity\thread") - so the scan finds the same class under
 *   a second name.
 *
 * API Platform used to let the second declaration silently replace the first;
 * since 4.3.18 it refuses, and the router - so the whole kernel - fails.
 *
 * So entries naming the same class collapse into one, under its real name,
 * and a Base\ resource that an App\ class extends gives way to it: the
 * override is the resource the application means. A Base\ resource only
 * extended under another name (App\Entity\Article extends Base\Entity\Thread)
 * stays, since their operations do not collide.
 */
final class OverriddenResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    private const BASE_NAMESPACE = 'base\\';
    private const APP_NAMESPACE = 'app\\';

    public function __construct(private readonly ResourceNameCollectionFactoryInterface $decorated)
    {
    }

    public function create(): ResourceNameCollection
    {
        // One entry per real class, keyed case-insensitively like PHP does.
        $classes = [];
        foreach ($this->decorated->create() as $name) {
            $class = class_exists($name) ? (new \ReflectionClass($name))->getName() : $name;
            $classes[strtolower($class)] ??= $class;
        }

        $kept = array_filter($classes, static function (string $class, string $key) use ($classes): bool {
            if (!str_starts_with($key, self::BASE_NAMESPACE)) {
                return true;
            }

            $override = $classes[self::APP_NAMESPACE.substr($key, strlen(self::BASE_NAMESPACE))] ?? null;

            return !(null !== $override && is_subclass_of($override, $class));
        }, ARRAY_FILTER_USE_BOTH);

        return new ResourceNameCollection(array_values($kept));
    }
}
