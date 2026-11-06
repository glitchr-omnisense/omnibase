<?php

namespace Base\Database\Repository;

/**
 * A repository method that only one persistence runs (plan "omnibase agnostic", step E2): its
 * query is no finder (Base\Database\Repository\Finder) but the backend's own language - native
 * SQL with an upsert or a DATE() grouping, a bulk DQL UPDATE -, so another persistence does not
 * compile it from the core: it writes the method again in its own terms, or refuses it aloud
 * with refuse(), never answers it silently wrong.
 *
 * Declared on the core's interface method (Base\Repository\Analytics\PageViews::dailyBreakdown())
 * and on the implementation that runs it (omnibridge/doctrine's PageViewRepository): declared()
 * lists them, the inventory omnibridge/eloquent works from (F7b), and the core's
 * BackendOnlyTest keeps that list as recorded.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class BackendOnly
{
    /** @var list<string> the persistences that run the method ("doctrine") */
    public readonly array $backends;

    /**
     * @param string|list<string> $backends
     * @param string              $reason   what the query needs of that backend ("ON DUPLICATE KEY UPDATE")
     */
    public function __construct(string|array $backends, public readonly string $reason = '')
    {
        $this->backends = array_values((array) $backends);
    }

    /** The declaration of $class::$method, on the method itself or on one of the interfaces it implements. */
    public static function of(string $class, string $method): ?self
    {
        if (!method_exists($class, $method)) {
            return null;
        }

        $classes = array_merge([$class], array_values(class_implements($class) ?: []));
        foreach ($classes as $candidate) {
            if (!method_exists($candidate, $method)) {
                continue;
            }
            foreach ((new \ReflectionMethod($candidate, $method))->getAttributes(self::class) as $attribute) {
                return $attribute->newInstance();
            }
        }

        return null;
    }

    /** Whether $backend runs $class::$method: true for any method not declared backend-only. */
    public static function runs(string $class, string $method, string $backend): bool
    {
        $declaration = self::of($class, $method);

        return null === $declaration || \in_array($backend, $declaration->backends, true);
    }

    /**
     * Refuses aloud, under $backend, a method declared for others: what a persistence's
     * implementation of the interface answers until it writes the query in its own terms.
     *
     * @throws UnsupportedQueryException always
     */
    public static function refuse(string $class, string $method, string $backend): never
    {
        $declaration = self::of($class, $method);
        $backends = null === $declaration ? 'another persistence' : implode(', ', $declaration->backends);
        $reason = null === $declaration || '' === $declaration->reason ? '' : ' ('.$declaration->reason.')';

        throw new UnsupportedQueryException(sprintf('%s::%s() is written for %s only%s; the "%s" persistence does not run it.', $class, $method, $backends, $reason, $backend));
    }

    /**
     * Every method of these classes or interfaces declared backend-only, "Class::method" => declaration.
     *
     * @param iterable<class-string> $classes
     *
     * @return array<string, self>
     */
    public static function declared(iterable $classes): array
    {
        $declared = [];
        foreach ($classes as $class) {
            foreach ((new \ReflectionClass($class))->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                foreach ($method->getAttributes(self::class) as $attribute) {
                    $declared[$class.'::'.$method->getName()] = $attribute->newInstance();
                }
            }
        }
        ksort($declared);

        return $declared;
    }
}
