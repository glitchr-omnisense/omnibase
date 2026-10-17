<?php

namespace Tests\Base\Attributes;

use Base\Attributes\AttributeReader;
use Base\Service\ArrayParameterBag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The folders of base.attributes.paths are read: the AttributeReader looks
 * there for attributes (an application's src/Attribute, a bundle's - omnibase/
 * marketplace prepends its own). The pattern that picked them out of the
 * parameters wanted a dot after the item's number (base.attributes.paths.0.),
 * which no key has: none was ever read, and getPaths() answered [] as well.
 */
final class AttributePathsTest extends KernelTestCase
{
    public function testTheConfiguredFoldersArePickedOut(): void
    {
        self::assertSame([], AttributeReader::configuredPaths(new ArrayParameterBag(['base.attributes.use_custom' => true])));
        self::assertSame(['/srv/app/src/Attribute', '/srv/bundle/src/Attribute'], AttributeReader::configuredPaths(new ArrayParameterBag([
            'base.attributes.paths' => ['/srv/app/src/Attribute', '/srv/bundle/src/Attribute'],
            'base.attributes.paths.0' => '/srv/app/src/Attribute',
            'base.attributes.paths.1' => '/srv/bundle/src/Attribute',
            'base.attributes.paths_other.0' => '/not/this',
        ])), 'the list whole and item by item, once each');
        self::assertSame(['/srv/app/src/Attribute'], AttributeReader::configuredPaths(new ArrayParameterBag(['base.attributes.paths.0' => '/srv/app/src/Attribute'])), 'item by item alone, as an entity manager built by hand gives them');
    }

    public function testTheReaderLooksInThem(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        self::bootKernel();
        $container = static::getContainer();
        $configured = array_values(array_filter((array) $container->getParameter('base.attributes.paths'), 'file_exists'));
        if (!$configured) {
            self::markTestSkipped('No folder of base.attributes.paths on this disk.');
        }

        $reader = $container->get(AttributeReader::class);
        foreach ($configured as $path) {
            self::assertContains($path, $reader->getPaths());
        }
        if (class_exists(\Base\Marketplace\Attribute\OrderReference::class)) {
            self::assertContains(\Base\Marketplace\Attribute\OrderReference::class, $reader->getAttributeNames(), 'omnibase/marketplace\'s folder, prepended to base.attributes.paths');
        }
    }
}
