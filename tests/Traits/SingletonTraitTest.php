<?php

namespace Tests\Base\Traits;

use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Base\Fixtures\Singleton\NoArgSingleton;
use Tests\Base\Fixtures\Singleton\RequiresArgSingleton;

/**
 * Direct coverage of the mechanism behind a real production incident:
 * TrashFilter (constructed by Doctrine, not the DI container) reaches
 * AttributeReader purely through this trait's getInstance(). Nothing else
 * on a bare console path asked the container for it first, so the
 * singleton was never seeded — and AttributeReader's constructor requires
 * several injected services, so getInstance()'s own "new self()" fallback
 * failed too, silently, returning null instead of surfacing the error.
 * "Call to a member function getClassAttributes() on null" was that null,
 * one call later.
 */
class SingletonTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        // Each fixture's $_instance is a distinct static property (traits
        // don't share static storage across using classes), but it does
        // persist across tests within the same class — reset explicitly
        // via reflection (both constructors are protected/require args) so
        // tests stay independent of run order.
        foreach ([NoArgSingleton::class, RequiresArgSingleton::class] as $class) {
            (new ReflectionClass($class))->setStaticPropertyValue('_instance', null);
        }
    }

    public function testNoInstanceInitially(): void
    {
        $this->assertFalse(NoArgSingleton::hasInstance());
        $this->assertFalse(RequiresArgSingleton::hasInstance());
    }

    public function testGetInstanceAutoConstructsWhenConstructorNeedsNoArgs(): void
    {
        $instance = NoArgSingleton::getInstance();

        $this->assertInstanceOf(NoArgSingleton::class, $instance);
        $this->assertTrue(NoArgSingleton::hasInstance());
        $this->assertSame($instance, NoArgSingleton::getInstance(), 'Repeated calls must return the same instance.');
    }

    public function testGetInstanceFalseNeverConstructs(): void
    {
        $this->assertNull(NoArgSingleton::getInstance(false));
        $this->assertFalse(NoArgSingleton::hasInstance());
    }

    public function testSetInstanceIsHonoredByGetInstance(): void
    {
        // The constructor is protected (inherited from the trait): go through
        // the auto-constructing getInstance() to get a real instance, then
        // exercise setInstance() with it explicitly.
        $instance = NoArgSingleton::getInstance();
        NoArgSingleton::setInstance($instance);

        $this->assertTrue(NoArgSingleton::hasInstance());
        $this->assertSame($instance, NoArgSingleton::getInstance());
    }

    /**
     * The regression: a required-argument constructor makes the auto-
     * construct fallback fail. getInstance() must swallow that into null,
     * not let ArgumentCountError escape uncaught.
     */
    public function testGetInstanceReturnsNullRatherThanThrowingWhenConstructorNeedsArgs(): void
    {
        $this->assertNull(RequiresArgSingleton::getInstance());
        $this->assertFalse(
            RequiresArgSingleton::hasInstance(),
            'A failed auto-construct attempt must not leave a half-set instance behind.'
        );
    }

    public function testGetInstanceStillWorksOncePreSeededDespiteRequiredArgs(): void
    {
        $instance = new RequiresArgSingleton('configured');
        RequiresArgSingleton::setInstance($instance);

        $this->assertSame($instance, RequiresArgSingleton::getInstance());
        $this->assertSame('configured', RequiresArgSingleton::getInstance()->dependency);
    }

    public function testInstanceStorageIsIndependentPerUsingClass(): void
    {
        NoArgSingleton::getInstance();

        $this->assertTrue(NoArgSingleton::hasInstance());
        $this->assertFalse(RequiresArgSingleton::hasInstance());
    }

    public function testCloneSleepWakeupAreProtectedAgainst(): void
    {
        $instance = NoArgSingleton::getInstance();

        foreach (['__clone', '__sleep', '__wakeup'] as $method) {
            try {
                $instance->$method();
                $this->fail("$method() was expected to throw.");
            } catch (Exception $e) {
                $this->assertStringContainsString('singleton pattern', $e->getMessage());
            }
        }
    }
}
