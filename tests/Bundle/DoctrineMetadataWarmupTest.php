<?php

namespace Tests\Base\Bundle;

use Omnibridge\Symfony\Omnibase\Cache\Warmer\DoctrineMetadataCacheWarmer;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\DoctrineConfigurationPass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * `cache:warmup` and `cache:clear` without debug stopped on DoctrineBundle's metadata cache warmer ("must load metadata
 * first"): under omnibase the entity manager has always loaded metadata by then (the settings read at each command's
 * start, the reader's and the manipulator's own warmers). omnibridge/symfony's DoctrineMetadataCacheWarmer, put in its
 * place, writes the metadata's PHP file all the same, every entity in it.
 */
final class DoctrineMetadataWarmupTest extends KernelTestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/omnibase-metadata-'.bin2hex(random_bytes(6)).'.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testThePassPutsItInPlaceOfDoctrineBundles(): void
    {
        if (!class_exists(DoctrineMetadataCacheWarmer::class)) {
            self::markTestSkipped('Requires omnibridge/symfony\'s DoctrineMetadataCacheWarmer.');
        }
        $container = new ContainerBuilder();
        $container->setDefinition('doctrine.orm.configuration', new Definition(\stdClass::class));
        $container->setParameter('doctrine.entity_managers', ['default' => 'doctrine.orm.default_entity_manager']);
        $container->setDefinition('doctrine.orm.default_configuration', new Definition(\stdClass::class));
        $container->setDefinition('doctrine.orm.default_metadata_cache_warmer', new Definition(DoctrineConfigurationPass::DOCTRINE_METADATA_CACHE_WARMER, [new Reference('doctrine.orm.default_entity_manager'), '%kernel.cache_dir%/doctrine/orm/default_metadata.php']));
        (new DoctrineConfigurationPass())->process($container);

        $warmer = $container->getDefinition('doctrine.orm.default_metadata_cache_warmer');
        self::assertSame(DoctrineMetadataCacheWarmer::class, $warmer->getClass());
        self::assertSame('%kernel.cache_dir%/doctrine/orm/default_metadata.php', $warmer->getArgument(1), 'its arguments kept');
    }

    public function testItWarmsOnceMetadataIsLoaded(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists(DoctrineMetadataCacheWarmer::class)) {
            self::markTestSkipped('Requires the host application kernel and omnibridge/symfony\'s DoctrineMetadataCacheWarmer.');
        }
        self::bootKernel();
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $loaded = $entityManager->getClassMetadata(\Base\Entity\User::class)->getName();
        self::assertNotEmpty($entityManager->getMetadataFactory()->getLoadedMetadata());

        if (class_exists(\Doctrine\Bundle\DoctrineBundle\CacheWarmer\DoctrineMetadataCacheWarmer::class)) {
            try {
                // Its warmUp() is AbstractPhpFileCacheWarmer's (the class is DoctrineBundle's @internal).
                $doctrine = new \Doctrine\Bundle\DoctrineBundle\CacheWarmer\DoctrineMetadataCacheWarmer($entityManager, $this->file);
                (new \ReflectionMethod($doctrine, 'warmUp'))->invoke($doctrine, \dirname($this->file));
                self::fail('DoctrineBundle\'s warmer accepts loaded metadata now: this one is no longer needed.');
            } catch (\LogicException $e) {
                self::assertStringContainsString('must load metadata first', $e->getMessage());
            }
            @unlink($this->file);
        }

        (new DoctrineMetadataCacheWarmer($entityManager, $this->file))->warmUp(\dirname($this->file));
        self::assertFileExists($this->file);

        $cache = new PhpArrayAdapter($this->file, new NullAdapter());
        $all = $entityManager->getMetadataFactory()->getAllMetadata();
        self::assertGreaterThan(10, \count($all));
        foreach ([$loaded, $all[array_key_last($all)]->getName()] as $class) {
            self::assertTrue($cache->hasItem(str_replace('\\', '__', $class).'__CLASSMETADATA__'), $class.' in the file');
        }

        // Written once: a second warm-up leaves the file as it is.
        self::assertSame([], (new DoctrineMetadataCacheWarmer($entityManager, $this->file))->warmUp(\dirname($this->file)));
    }
}
