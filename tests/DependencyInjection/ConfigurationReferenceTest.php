<?php

namespace Tests\Base\DependencyInjection;

use Base\DependencyInjection\BaseConfiguration;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * config:dump-reference base and debug:config base show the tree: they failed with "The
 * extension with alias "base" does not have its getConfiguration() method setup" (Symfony looks
 * for a class named Configuration, the core's is BaseConfiguration). The options no code reads
 * are deprecated, not removed: every site's base.yaml still sets most of them.
 */
final class ConfigurationReferenceTest extends KernelTestCase
{
    /** Read by nothing: deprecated, removed at the next major. */
    private const UNREAD = [
        'base.database.excluded_fields',
        'base.share',
        'base.access_restriction.redirect_on_deny',
        'base.site.maintenance.exception',
        'base.uploader.warmup',
        'base.files.storage',
        'base.images.autorotate',
        'base.images.disable_profiler',
        'base.images.warmup',
        'base.images.formats',
        'base.extension.logging_default_expiry',
    ];

    public function testTheReferenceAndTheConfigurationAreDumped(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness).');
        }
        self::bootKernel();
        $application = new Application(static::$kernel);

        $reference = new CommandTester($application->find('config:dump-reference'));
        self::assertSame(0, $reference->execute(['name' => 'base']), $reference->getDisplay());
        self::assertStringContainsString('parameter_bag:', $reference->getDisplay());

        $debug = new CommandTester($application->find('debug:config'));
        self::assertSame(0, $debug->execute(['name' => 'base']), $debug->getDisplay());
        self::assertStringContainsString('use_hot_bag: true', $debug->getDisplay());
    }

    public function testTheOptionsReadByNothingAreDeprecated(): void
    {
        $tree = (new BaseConfiguration())->getConfigTreeBuilder()->buildTree();
        foreach (self::UNREAD as $path) {
            $node = $tree;
            foreach (\array_slice(explode('.', $path), 1) as $name) {
                self::assertInstanceOf(ArrayNode::class, $node, $path);
                $node = $node->getChildren()[$name] ?? null;
                self::assertInstanceOf(NodeInterface::class, $node, $path);
            }
            self::assertTrue($node->isDeprecated(), $path.' is deprecated');
        }
        self::assertFalse($tree->getChildren()['uploader']->getChildren()['formats']->isDeprecated(), 'base.uploader.formats is read (uploader:images)');
    }
}
