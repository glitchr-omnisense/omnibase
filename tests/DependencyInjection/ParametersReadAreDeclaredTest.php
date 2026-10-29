<?php

namespace Tests\Base\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every "base.*" key the core reads from the parameter bag, in its code and its templates, is a
 * parameter its configuration declares: a key read and never declared is always null, so its
 * option had no effect (base.user.autoapprove for base.user.register.autoapprove,
 * base.maintenance.lockpath for base.site.maintenance.lockpath, base.site.homepage for
 * base.site.index, base.notifier.technical_support for base.notifier.technical_recipient.email,
 * base.router.use_fallback for base.router.use_fallbacks; base.user.register.* were declared, but
 * not given their defaults when the site configured nothing under register).
 */
final class ParametersReadAreDeclaredTest extends KernelTestCase
{
    /**
     * Read and never declared, each read with a fallback (null: the caller's own default), left
     * for a later pass: options that never existed in this tree (a breadcrumb's classes, the
     * themes' default mode, sortablejs' script for FileType).
     */
    private const LEFT = [
        'base.breadcrumb.class',
        'base.breadcrumb.class_item',
        'base.breadcrumb.separator',
        'base.twig.themes.mode',
        'base.vendor.sortablejs.javascript',
    ];

    public function testEveryKeyReadIsDeclared(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness).');
        }
        self::bootKernel();
        $container = static::$kernel->getContainer(); // its parameters, a null one included (the bag's has() says no to null)

        $read = [];
        $root = \dirname(__DIR__, 2);
        foreach (['src', 'templates'] as $directory) foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!is_file((string) $file)) {
                continue;
            }
            $code = (string) file_get_contents((string) $file);
            // $this->parameterBag->get("base.x"), getParameterBag("base.x"), app.bag("base.x")
            preg_match_all('/(?:parameterBag->get|getParameterBag|\.bag)\(\s*["\'](base\.[a-z0-9_.]+)["\']/i', $code, $matches);
            foreach ($matches[1] as $key) {
                $read[$key][] = substr((string) $file, \strlen($root) + 1);
            }
        }
        self::assertNotEmpty($read);

        $undeclared = [];
        foreach ($read as $key => $where) {
            if (!$container->hasParameter($key) && !\in_array($key, self::LEFT, true)) {
                $undeclared[] = $key.' ('.implode(', ', array_unique($where)).')';
            }
        }
        sort($undeclared);

        self::assertSame([], $undeclared, 'read, never declared');
    }
}
