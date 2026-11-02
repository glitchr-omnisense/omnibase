<?php

namespace Tests\Base\Service;

use Base\Service\ArrayParameterBag;
use Base\Service\HotParameters;
use PHPUnit\Framework\TestCase;

/**
 * The settings stored in the database laid over the configuration (plan
 * "omnibase agnostique", C9): Base\Service\HotParameters, framework-free, over
 * any omnibase ParameterBagInterface - here an ArrayParameterBag. The order is
 * the $bag given, the settings, the configuration, by the presence of a value
 * and not by its truth; without the overlay, the configuration is read.
 */
final class HotParametersTest extends TestCase
{
    private static function config(?bool $hot = true): ArrayParameterBag
    {
        return new ArrayParameterBag(array_filter([
            'base.parameter_bag.use_hot_bag' => $hot,
            'base.user.login_with_token' => true,
            'base.user.active_delay' => 300,
            'base.site.title' => 'Les Ringo Stars',
            'base.site.slogan' => 'En route',
            'base.twig.breakpoints' => ['sm' => 576, 'md' => 768],
        ], static fn ($value) => null !== $value));
    }

    public function testAFalseSettingOverridesTheConfigurationsTrue(): void
    {
        $bag = new HotParameters(self::config());
        $bag->add(['base.user.login_with_token' => false, 'base.user.active_delay' => 0, 'base.site.slogan' => '']);

        self::assertFalse($bag->get('base.user.login_with_token'));
        self::assertSame(0, $bag->get('base.user.active_delay'));
        self::assertSame('', $bag->get('base.site.slogan'));
        self::assertTrue($bag->has('base.user.login_with_token'), 'a false setting is there');
        self::assertFalse($bag->all()['base.user.login_with_token']);
    }

    public function testANullSettingIsNoValue(): void
    {
        $bag = new HotParameters(self::config());
        $bag->add(['base.site.title' => null, 'google.tag_manager.containers.default.id' => null]);

        self::assertSame('Les Ringo Stars', $bag->get('base.site.title'));
        self::assertNull($bag->get('google.tag_manager.containers.default.id'));
        self::assertFalse($bag->has('google.tag_manager.containers.default.id'));
        self::assertSame('Les Ringo Stars', $bag->all()['base.site.title']);
    }

    public function testTheSourcesInOrder(): void
    {
        $bag = new HotParameters(self::config());
        $bag->add(['base.site.title' => 'The Ringo Stars']);

        self::assertSame('The Ringo Stars', $bag->get('base.site.title'), 'the setting over the configuration');
        self::assertSame('Ringo', $bag->get('base.site.title', ['base.site.title' => 'Ringo']), 'the bag given over the setting');
        self::assertSame('The Ringo Stars', $bag->get('base.site.title', ['base.site.title' => null]), 'a null in the bag given is no value');
        self::assertSame('Les Ringo Stars', $bag->get('base.site.title', null, false), 'the configuration alone when the settings are not asked');
        self::assertSame(['title' => 'Ringo', 'slogan' => 'En route'], $bag->get('base.site', ['base.site.title' => 'Ringo']), 'a path naming values: each from the first source that has it');
        self::assertSame(['sm' => 576, 'md' => 720], $bag->get('base.twig.breakpoints', ['base.twig.breakpoints.md' => 720]));
        self::assertTrue($bag->has('base.site.motto', ['base.site.motto' => 'Yeah']), 'has() reads the bag given');
        self::assertFalse($bag->has('base.site.motto'));
    }

    public function testWithoutTheOverlayTheConfigurationIsRead(): void
    {
        foreach ([new HotParameters(self::config(null)), new HotParameters(self::config(false)), new HotParameters(self::config(), false)] as $bag) {
            $bag->add(['base.site.title' => 'The Ringo Stars']);

            self::assertFalse($bag->isEnabled());
            self::assertSame('Les Ringo Stars', $bag->get('base.site.title'), 'the configuration, not null, and not the setting');
            self::assertSame(300, $bag->get('base.user.active_delay'));
            self::assertTrue($bag->has('base.user.active_delay'));
            self::assertSame('Ringo', $bag->get('base.site.title', ['base.site.title' => 'Ringo']));
            self::assertSame('Les Ringo Stars', $bag->all()['base.site.title']);
        }
    }

    public function testItTakesTheSettingsInWithoutAnException(): void
    {
        $bag = new HotParameters(self::config());
        self::assertFalse($bag->isReady());

        $bag->add(['base.site.title' => 'The Ringo Stars']);
        $bag->add(['base.site.slogan' => 'Again']);
        $bag->markAsReady();

        self::assertTrue($bag->isReady());
        self::assertSame(['title' => 'The Ringo Stars', 'slogan' => 'Again'], $bag->get('base.site'));
    }

    public function testSetWritesIntoTheBagGiven(): void
    {
        $bag = new HotParameters(self::config());
        $values = [];
        $bag->set('base.site.title', 'Ringo', $values);

        self::assertSame(['base' => ['site' => ['title' => 'Ringo']]], $values);
    }

    /**
     * warm(): the settings taken in once, from a SettingBag or a callable (plan "omnibase agnostic", step F5c:
     * what omnibridge/symfony's HotParameterBagSubscriber does on the first request, any host can); a store that
     * fails leaves the configuration, not ready.
     */
    public function testWarmTakesTheSettingsInOnce(): void
    {
        $bag = new HotParameters(self::config());
        $asked = 0;
        $settings = static function () use (&$asked): array {
            ++$asked;

            return ['base.site.title' => 'Ringo'];
        };

        self::assertTrue($bag->warm($settings));
        self::assertFalse($bag->warm($settings), 'once');
        self::assertSame(1, $asked);
        self::assertTrue($bag->isReady());
        self::assertSame('Ringo', $bag->get('base.site.title'));
        self::assertInstanceOf(\Base\Service\HotParameterBagInterface::class, $bag);

        $failing = new HotParameters(self::config());
        try {
            $failing->warm(static fn (): array => throw new \RuntimeException('no database yet'));
            self::fail('the store\'s error is the host\'s');
        } catch (\RuntimeException) {
        }
        self::assertFalse($failing->isReady());
        self::assertSame('Les Ringo Stars', $failing->get('base.site.title'));
    }
}
