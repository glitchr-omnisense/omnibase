<?php

namespace Tests\Base\Options;

use Base\Options;
use Base\Services;
use Omnibridge\Options\Processor;
use PHPUnit\Framework\TestCase;

/**
 * The declaration of the options (Base\Options, step F5e slice 4b) on its own: every node in Processor's format,
 * the parameters a host is given, the environment's defaults by the group of services that reads them.
 */
final class OptionsTest extends TestCase
{
    private const KEYS = ['type', 'info', 'default', 'deprecated', 'min', 'max', 'values', 'children', 'defaults', 'prototype', 'key', 'default_children', 'deep_merging'];

    public function testEveryNodeIsInTheProcessorsFormat(): void
    {
        $count = 0;
        $walk = function (array $node, string $path) use (&$walk, &$count): void {
            ++$count;
            self::assertContains($node['type'] ?? null, Processor::TYPES, $path);
            self::assertSame([], array_diff(array_keys($node), self::KEYS), $path);
            self::assertFalse(isset($node['children'], $node['prototype']), $path.': children or a prototype, not both');
            if (isset($node['deprecated'])) {
                self::assertEqualsCanonicalizing(['package', 'since', 'message'], array_keys($node['deprecated']), $path);
            }
            foreach ($node['children'] ?? [] as $name => $child) {
                $walk($child, $path.'.'.$name);
            }
            if (isset($node['prototype'])) {
                $walk($node['prototype'], $path.'.*');
            }
        };
        $walk(['type' => 'array', 'children' => Options::schema()], Options::ROOT);

        self::assertGreaterThan(200, $count);
    }

    public function testTheParametersAreTheDefaultsByFullName(): void
    {
        $parameters = Options::parameters();

        self::assertSame(3, $parameters['base.guard.min_delay']);
        self::assertSame(['ROLE_ADMIN'], $parameters['base.demo.admin_roles']);
        self::assertSame('ROLE_ADMIN', $parameters['base.demo.admin_roles.0']);
        self::assertSame(Options::defaults()['user'], $parameters['base.user']);
        self::assertSame('%kernel.project_dir%/public/maintenance.lock', $parameters['base.site.maintenance.lockpath'], 'the placeholders are the host\'s');
        self::assertSame(\dirname(__DIR__, 2).'/templates', $parameters['base.twig.default_path']);
        self::assertSame('polite', Options::parameters(['translator' => ['politeness' => 'polite']])['base.translator.politeness']);
        // The storages the core declares (discovery.php's `storages`), where the configuration names none: a host
        // without base.yaml no longer stops on a null storage (Base\Database\Attribute\Uploader::$storage).
        self::assertSame('local.uploads', $parameters['base.uploader.storage']);
        self::assertSame('local.wysiwyg', $parameters['base.twig.editor.storage']);
    }

    public function testTheEnvironmentsDefaultsBelongToAGroupOfServices(): void
    {
        self::assertSame(['COLLAB_TICKET_SECRET' => '', 'COLLAB_RELAY_WS_URL' => ''], Options::environment('collab'));
        self::assertSame(['BASE_TRADING_LIVE' => 'true'], Options::environment('media'));
        self::assertSame(['env(COLLAB_TICKET_SECRET)' => '', 'env(COLLAB_RELAY_WS_URL)' => '', 'env(BASE_TRADING_LIVE)' => 'true'], Options::environmentParameters());
        foreach (array_keys(Options::environment()) as $name) {
            $group = null;
            foreach (Services::GROUPS as $each) {
                if (\array_key_exists($name, Options::environment($each))) {
                    $group = $each;
                }
            }
            self::assertNotNull($group, $name);
        }
    }
}
