<?php

namespace Tests\Base\Twig\Variable;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The site's and the e-mail's Twig variables read the back office's settings
 * themselves (plan "omnibase agnostique", C9), where they asked BaseService:
 * the same answers, BaseService::getSite()/getAdmin()/getEmail()/getMeta() as
 * they were written, and BaseService left unbuilt.
 */
final class SiteVariableTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheSitesNameAndMetaAreTheSettings(): void
    {
        $settings = static::getContainer()->get('setting_bag');
        $site = static::getContainer()->get('Base\\Twig\\Variable\\SiteVariable');

        self::assertSame($settings->getScalar('base.settings.title'), $site->title());
        self::assertSame($settings->getScalar('base.settings.slogan'), $site->slogan());
        self::assertSame($settings->getScalar('base.settings.logo'), $site->logo());
        self::assertSame(array_merge($settings->get('base.settings.meta', 'en') ?? [], ['x' => 1]), $site->meta(['x' => 1], 'en'));
        self::assertSame(static::getContainer()->get('advanced_router')->getRouteIndex(), $site->index());
        self::assertIsFloat($site->execution_time());
        self::assertGreaterThanOrEqual(0, $site->execution_time());
    }

    public function testTheEmailsNameFallsBackOnTheSites(): void
    {
        $settings = static::getContainer()->get('setting_bag');
        $email = static::getContainer()->get('Base\\Twig\\Variable\\EmailVariable');

        self::assertSame($settings->getScalar('base.settings.title.email') ?? $settings->getScalar('base.settings.title'), $email->title());
        self::assertSame($settings->getScalar('base.settings.slogan.email') ?? $settings->getScalar('base.settings.slogan'), $email->slogan());
        self::assertSame($settings->getScalar('base.settings.logo.email') ?? $settings->getScalar('base.settings.logo'), $email->logo());
        self::assertSame($settings->getScalar('base.settings.mail.contact'), $email->address());
        self::assertSame(static::getContainer()->get('advanced_router')->getRouteIndex(), $email->homepage());
    }

    public function testNeitherBuildsBaseService(): void
    {
        static::getContainer()->get('Base\\Twig\\Variable\\SiteVariable')->title();
        static::getContainer()->get('Base\\Twig\\Variable\\EmailVariable')->title();
        $service = static::getContainer()->get('base.service');

        self::assertTrue((new \ReflectionClass($service))->isUninitializedLazyObject($service));
    }
}
