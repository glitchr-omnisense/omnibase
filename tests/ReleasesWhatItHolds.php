<?php

namespace Tests\Base;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PHPUnit keeps every test case to the end of the run: what a kernel test holds
 * in its properties - an entity manager, a session, a service or an entity
 * taken from the container - would keep each kernel it booted alive with it
 * (3 MB a test in AnalyticsTest, 5 to 10 MB in the select's, the
 * signatures' and the translations' tests). Once the kernel is shut down,
 * the case's properties go.
 *
 * A case with a tearDown() of its own calls releaseWhatItHolds() after
 * parent::tearDown().
 */
trait ReleasesWhatItHolds
{
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->releaseWhatItHolds();
    }

    protected function releaseWhatItHolds(): void
    {
        for ($class = new \ReflectionClass($this); $class && KernelTestCase::class !== $class->getName(); $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $class->getName() || $property->isStatic() || $property->isReadOnly() || !$property->isInitialized($this)) {
                    continue;
                }
                $name = $property->getName();
                \Closure::bind(function () use ($name): void { unset($this->{$name}); }, $this, $class->getName())();
            }
        }
    }
}
