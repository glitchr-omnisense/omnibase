<?php

namespace Tests\Base\Routing;

use Base\DependencyInjection\Compiler\Pass\AdminUrlGeneratorPass;
use Base\Routing\AdminUrlGeneratorInterface;
use Base\Routing\NullAdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * base-bundle is installable without a back-office, so the admin URL builder
 * its form types and Twig helpers depend on has to resolve either way. These
 * cases pin that down at the container level, where the real regression would
 * be a boot failure rather than a wrong URL.
 *
 * Pure ContainerBuilder unit test - no kernel, so it also passes in an
 * install that genuinely has no admin.
 */
class AdminUrlGeneratorPassTest extends TestCase
{
    private const ADMIN_GENERATOR = 'Base\Admin\Router\AdminUrlGenerator';

    private function compile(ContainerBuilder $container): ContainerBuilder
    {
        (new AdminUrlGeneratorPass())->process($container);

        return $container;
    }

    public function testFallsBackToTheNullGeneratorWhenNoAdminIsInstalled(): void
    {
        $container = $this->compile(new ContainerBuilder());

        $this->assertTrue($container->has(AdminUrlGeneratorInterface::class));
        $this->assertSame(
            NullAdminUrlGenerator::class,
            $container->getDefinition(AdminUrlGeneratorInterface::class)->getClass()
        );
    }

    public function testBindsToTheAdminGeneratorWhenTheAdminIsInstalled(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(self::ADMIN_GENERATOR, new Definition(self::ADMIN_GENERATOR));

        $this->compile($container);

        $this->assertTrue($container->hasAlias(AdminUrlGeneratorInterface::class));
        $this->assertSame(
            self::ADMIN_GENERATOR,
            (string) $container->getAlias(AdminUrlGeneratorInterface::class)
        );
    }

    public function testAnApplicationBindingOfItsOwnIsNeverOverridden(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(self::ADMIN_GENERATOR, new Definition(self::ADMIN_GENERATOR));
        $container->setDefinition(AdminUrlGeneratorInterface::class, new Definition('App\CustomAdminUrlGenerator'));

        $this->compile($container);

        $this->assertSame(
            'App\CustomAdminUrlGenerator',
            $container->getDefinition(AdminUrlGeneratorInterface::class)->getClass()
        );
    }

    /**
     * Every call site builds fluently and only then asks for the string, so
     * the no-op must stay chainable and fail at exactly one point.
     */
    public function testTheNullGeneratorStaysChainableAndFailsOnlyAtGeneration(): void
    {
        $generator = new NullAdminUrlGenerator();

        $built = $generator->unsetAll()
            ->setController('App\Controller\Admin\Crud\Article\ArticleCrudController')
            ->setAction('edit')
            ->setEntityId(1)
            ->set('foo', 'bar')
            ->unset('foo');

        $this->assertInstanceOf(AdminUrlGeneratorInterface::class, $built);

        $this->expectException(\LogicException::class);
        $built->generateUrl();
    }
}
