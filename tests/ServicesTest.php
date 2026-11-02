<?php

namespace Tests\Base;

use Base\Services;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Http\HttpTestTrait;

/**
 * Base\Services::definitions() and Symfony's container agree (plan "omnibase agnostic", step F5a):
 * every service the list defines is in the container, of its class, and every contract it lists
 * answers that same service - aliased by the configuration or by config/services-list.php. The list
 * is also well formed: classes that exist, '@' references to services it defines or the host's
 * documented ids.
 */
final class ServicesTest extends KernelTestCase
{
    use HttpTestTrait;

    /** The ids the list names that the host gives (docs/20-architecture/services.md). */
    private const HOST_IDS = ['imagine.bitmap', 'imagine.svg', 'Symfony\Component\Translation\Translator'];

    public function testTheListIsWellFormed(): void
    {
        $ids = [];
        foreach (Services::definitions() as $definition) {
            self::assertTrue(class_exists($definition['class']), $definition['class']);
            $ids[] = $definition['id'] ?? $definition['class'];
        }
        self::assertSame(array_unique($ids), $ids, 'one definition per id');

        foreach (Services::definitions() as $definition) {
            $arguments = $definition['arguments'] ?? [];
            array_walk_recursive($arguments, static function (mixed $value) use ($ids, $definition): void {
                if (\is_string($value) && str_starts_with($value, '@') && !str_starts_with($value, '@@')) {
                    self::assertContains(substr($value, 1), [...$ids, ...self::HOST_IDS], $definition['class'].' names a service nobody defines');
                }
            });
        }
    }

    public function testSymfonysContainerAnswersTheListedServicesAndContracts(): void
    {
        $this->bootHost();
        $container = static::getContainer();

        foreach (Services::definitions() as $definition) {
            $id = $definition['id'] ?? $definition['class'];
            self::assertTrue($container->has($id), $id.' is a service under Symfony');
            $service = $container->get($id);
            self::assertInstanceOf($definition['class'], $service);
            foreach ($definition['contracts'] ?? [] as $contract) {
                self::assertSame($service, $container->get($contract), $contract.' answers '.$id);
            }
        }
    }
}
