<?php

namespace Tests\Base\Service;

use Base\Routing\AdvancedRouterInterface;
use Base\Service\Breadgrinder;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use PHPUnit\Framework\TestCase;

class BreadgrinderTest extends TestCase
{
    private AdvancedRouterInterface $router;
    private TranslatorInterface $translator;
    private ParameterBagInterface $parameterBag;

    protected function setUp(): void
    {
        $this->router = $this->createMock(AdvancedRouterInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->parameterBag->method('get')->willReturnCallback(fn(string $key) => [
            'base.breadcrumb.class' => 'breadcrumb',
            'base.breadcrumb.class_item' => 'breadcrumb-item',
            'base.breadcrumb.separator' => '/',
        ][$key] ?? null);
    }

    private function grinder(): Breadgrinder
    {
        return new Breadgrinder($this->router, $this->translator, $this->parameterBag);
    }

    public function testHasIsFalseUntilGrindIsCalled(): void
    {
        $grinder = $this->grinder();

        $this->assertFalse($grinder->has('main'));
        $grinder->grind('main');
        $this->assertTrue($grinder->has('main'));
    }

    public function testGrindReturnsTheSameInstanceForTheSameName(): void
    {
        $grinder = $this->grinder();

        $this->assertSame($grinder->grind('main'), $grinder->grind('main'));
    }

    public function testGrindReturnsDifferentInstancesForDifferentNames(): void
    {
        $grinder = $this->grinder();

        $this->assertNotSame($grinder->grind('main'), $grinder->grind('footer'));
    }

    public function testGrindAppliesParameterBagDefaultsOnlyOnFirstCreation(): void
    {
        $this->parameterBag->expects($this->exactly(3))->method('get');

        $grinder = $this->grinder();
        $breadcrumb = $grinder->grind('main');

        $this->assertSame('breadcrumb', $breadcrumb->getOption('class'));
        $this->assertSame('breadcrumb-item', $breadcrumb->getOption('class_item'));
        $this->assertSame('/', $breadcrumb->getOption('separator'));

        // Second grind() of the same name must not touch the parameter bag again.
        $grinder->grind('main');
    }

    public function testExplicitOptionsTakePrecedenceOverParameterBagDefaults(): void
    {
        $grinder = $this->grinder();
        $breadcrumb = $grinder->grind('main', ['class' => 'custom-class']);

        $this->assertSame('custom-class', $breadcrumb->getOption('class'));
        $this->assertSame('breadcrumb-item', $breadcrumb->getOption('class_item'));
    }

    public function testOptionsPassedOnASubsequentGrindAreAppliedToTheExistingBreadcrumb(): void
    {
        $grinder = $this->grinder();
        $grinder->grind('main');
        $breadcrumb = $grinder->grind('main', ['offset' => 2]);

        $this->assertSame(2, $breadcrumb->getOption('offset'));
        // Original defaults from the first call are preserved.
        $this->assertSame('breadcrumb', $breadcrumb->getOption('class'));
    }

    public function testTemplateIsOnlySetWhenProvided(): void
    {
        $grinder = $this->grinder();
        $default = $grinder->grind('main')->getTemplate();

        $this->assertSame($default, $grinder->grind('main')->getTemplate());

        $breadcrumb = $grinder->grind('other', [], '@Custom/breadcrumb.html.twig');
        $this->assertSame('@Custom/breadcrumb.html.twig', $breadcrumb->getTemplate());
    }
}
