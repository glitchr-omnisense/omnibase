<?php

namespace Tests\Base;

use Base\Services;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Http\HttpTestTrait;

/**
 * Base\Services::definitions() and Symfony's container agree (plan "omnibase agnostic", steps F5a and F5e slice 4):
 * every public service the declaration defines is in the container, of its class, and every public contract it lists
 * answers that same service. The declaration is also well formed: classes that exist (through their parents), one
 * definition per id, the neutral parts' '@' references to services it defines or the host's (its contracts, its
 * repositories, the ids docs/20-architecture/services.md names); its groups are what omnibridge/symfony compiles.
 */
final class ServicesTest extends KernelTestCase
{
    use HttpTestTrait;

    /** The ids the declaration names that the host gives (docs/20-architecture/services.md). */
    private const HOST_IDS = ['imagine.bitmap', 'Symfony\Component\Translation\Translator', 'Base\Routing\AdminUrlGeneratorInterface'];

    /** The families of the host's contracts and repositories, by their names' prefix. */
    private const HOST_PREFIXES = ['Omnibridge\\Contract\\', 'Omnibridge\\Persistence\\', 'Omniauth\\Contract\\', 'Base\\Contract\\', 'Base\\Repository\\', 'Base\\Database\\Repository\\'];

    public function testTheListIsWellFormed(): void
    {
        $ids = [];
        $known = [];
        foreach (Services::definitions() as $definition) {
            $class = Services::classOf($definition);
            if (null !== $class && Services::applies($definition, 'demo')) {
                self::assertTrue(class_exists($class), $class);
            }
            $ids[] = Services::id($definition);
            $known[Services::id($definition)] = true;
            foreach (Services::contracts($definition) as $contract => $options) {
                $known[$contract] = true;
            }
        }
        self::assertSame(array_unique($ids), $ids, 'one definition per id');
        self::assertSame(Services::definitions(), array_merge(...array_map(Services::group(...), Services::GROUPS)), 'the groups, in their order');

        foreach (Services::definitions() as $definition) {
            // The neutral parts, which Laravel's container and Omnibridge\Container read (`symfony` is Symfony's own).
            $neutral = [$definition['arguments'] ?? [], $definition['calls'] ?? [], $definition['bind'] ?? [], $definition['factory'] ?? null, $definition['parent'] ?? null];
            array_walk_recursive($neutral, static function (mixed $value) use ($known, $definition): void {
                if (!\is_string($value) || !str_starts_with($value, '@') || str_starts_with($value, '@@')) {
                    return;
                }
                $id = ltrim(substr($value, 1), '?!');
                if (isset($known[$id]) || '.inner' === $id || \in_array($id, self::HOST_IDS, true)) {
                    return;
                }
                foreach (self::HOST_PREFIXES as $prefix) {
                    if (str_starts_with($id, $prefix)) {
                        return;
                    }
                }
                self::fail(Services::id($definition).' names a service nobody defines: '.$id);
            });
            if (isset($definition['parent'])) {
                self::assertArrayHasKey($definition['parent'], $known, Services::id($definition).'\'s parent is the declaration\'s');
            }
        }
    }

    public function testSymfonysContainerAnswersTheListedServicesAndContracts(): void
    {
        $this->bootHost();
        $container = static::getContainer();
        $environment = (string) static::$kernel->getEnvironment();

        $answered = 0;
        foreach (Services::definitions() as $definition) {
            if (!Services::applies($definition, $environment) || !empty($definition['abstract']) || empty($definition['public'])) {
                continue;
            }
            $id = Services::id($definition);
            $class = Services::classOf($definition) ?? $id; // a child naming no class is its id's (Symfony's ResolveClassPass)
            if (!class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue; // an abstract class declared for its children (a parent under Symfony)
            }
            self::assertTrue($container->has($id), $id.' is a service under Symfony');
            $service = $container->get($id);
            self::assertInstanceOf($class, $service);
            foreach (Services::contracts($definition) as $contract => $options) {
                if (!empty($options['public'])) {
                    $answer = $container->get($contract);
                    // An interface a bundle binds to its own implementation (omnibase/admin's notification linker) answers that one.
                    if ($answer !== $service && interface_exists($contract) && $answer instanceof $contract) {
                        continue;
                    }
                    self::assertSame($service, $answer, $contract.' answers '.$id);
                }
            }
            ++$answered;
        }
        self::assertGreaterThan(100, $answered);
    }
}
