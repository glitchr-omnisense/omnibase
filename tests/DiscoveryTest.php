<?php

namespace Tests\Base;

use Base\Discovery;
use Base\Repository\Aliases;
use Base\Services;
use Omnibridge\Container\Declarations;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base\Discovery, what a host discovers among the core's services as data (plan "omnibase agnostic", step F5e slice 4c):
 * the registries are well formed - each kind there, the classes they name the core's or a package's it suggests, the
 * collecting services declared -, and omnibridge/symfony's one generic pass compiles them into Symfony's container as
 * the fifteen former passes did: the collections handed, the enumerations taken out of DoctrineBundle's types, the
 * contracts' locator, the admin's links, the guards' arguments. BareTest proves Omnibridge\Container discovers the same.
 */
final class DiscoveryTest extends KernelTestCase
{
    public function testTheRegistriesAreWellFormed(): void
    {
        self::assertSame(Discovery::KINDS, array_keys(Discovery::registries()));
        self::assertSame(Aliases::INTERFACES, Discovery::registry('repositories'));

        $declared = [];
        foreach (Services::definitions() as $definition) {
            $declared[Services::id($definition)] = true;
            foreach (array_keys(Services::contracts($definition)) as $contract) {
                $declared[$contract] = true;
            }
        }
        foreach (Discovery::registry('collect') as $collection) {
            self::assertArrayHasKey($collection['service'], $declared, 'a collecting service the core declares');
            self::assertTrue(isset($collection['tag']) xor isset($collection['instanceof']), $collection['service']);
            self::assertTrue(method_exists($collection['service'], $collection['method']) || method_exists((string) Services::find($collection['service'])['class'], $collection['method']), $collection['service'].'::'.$collection['method']);
        }
        foreach (Discovery::registry('autoconfigure') as $entry) {
            $known = class_exists($entry['instanceof']) || interface_exists($entry['instanceof']);
            self::assertTrue($known || !str_starts_with($entry['instanceof'], 'Base\\'), $entry['instanceof'].': the core\'s classes exist');
            self::assertNotSame([], $entry['tags']);
        }
        foreach (Discovery::registry('fallbacks') as $id => $fallback) {
            self::assertTrue(interface_exists($id), $id);
            self::assertTrue(is_a($fallback['default'], $id, true), $fallback['default']);
        }
        foreach (Discovery::registry('arguments') as $entry) {
            // The core's services (FormGuard) or a host's adapter of a core rule (Symfony's SignInGuard).
            self::assertStringStartsWith('Base\\', $entry['service']);
            self::assertContains($entry['type'], ['bool', 'string']);
        }
        $texts = Discovery::registry('texts');
        self::assertTrue(\is_callable($texts['reader']));
        self::assertSame(Discovery::registry('contracts')['contracts'], array_values(array_unique(Discovery::registry('contracts')['contracts'])));

        $this->expectException(\InvalidArgumentException::class);
        Discovery::registry('passes');
    }

    /** The core's enumerations, each by its name, read from the folders the registry names (no Doctrine). */
    public function testTheEnumerationsAreDiscoveredWithoutDoctrine(): void
    {
        $enumerations = Discovery::enumerations(sys_get_temp_dir().'/omnibase-no-project');
        self::assertGreaterThanOrEqual(20, \count($enumerations));
        foreach ($enumerations as $name => $class) {
            self::assertStringStartsWith('Base\\Enum\\', $class);
            self::assertTrue(is_subclass_of($class, Discovery::registry('enumerations')['extends']));
            self::assertSame($name, $class::getStaticName());
        }
    }

    /** What Symfony's container discovered: the generic pass compiled each registry. */
    public function testSymfonysContainerCompilesTheRegistries(): void
    {
        $container = static::getContainer();

        // collect: the compressions, the sharing adapters, as the declaration tags them.
        $resolved = Declarations::resolve(Services::definitions(), 'test');
        $obfuscator = $container->get('Base\Service\Obfuscator');
        self::assertSame(Declarations::tagged($resolved, 'obfuscator.compression'), array_keys($obfuscator->getCompressions()));
        $sharing = $container->get('Base\Service\Sharing');
        self::assertSame(Declarations::tagged($resolved, 'base.service.sharing'), array_keys((fn () => $this->adapters)->call($sharing)));

        // enumerations: out of DoctrineBundle's types, handed to the middleware, the core's all there.
        if ($container->hasParameter('base.database.enumerations')) {
            $found = $container->getParameter('base.database.enumerations');
            foreach (Discovery::enumerations($container->getParameter('kernel.project_dir')) as $name => $class) {
                self::assertSame($class, $found[$name] ?? null, $name);
            }
            foreach ($container->getParameter('doctrine.dbal.connection_factory.types') as $name => $type) {
                self::assertFalse(is_subclass_of($type['class'], Discovery::registry('enumerations')['extends']), $name.' is no Doctrine type');
            }
        }

        // contracts: the locator the Runtime reaches.
        $locator = $container->get(Discovery::registry('contracts')['service']);
        self::assertTrue($locator->has('Omnibridge\Contract\Urls'));

        // fallbacks: the admin's links answer, whichever back office is installed.
        self::assertInstanceOf('Base\Routing\AdminUrlGeneratorInterface', $container->get('Base\Routing\AdminUrlGeneratorInterface'));
    }
}
