<?php

namespace Tests\Base\Enum;

use Base\Enum\Quadrant\Quadrant;
use Base\Enum\Quadrant\Quadrant16;
use Base\Enum\Quadrant\Quadrant8;
use PHPUnit\Framework\TestCase;

/**
 * Quadrant8/16 refine Quadrant by halving theta and adding intermediate
 * directions, but the parent rotations must keep the parent's own theta:
 * Quadrant::getRotations() deliberately calls self::getTheta() (bound to
 * Quadrant), not static:: — otherwise NORTH would collapse onto NORTHEAST
 * once inherited. These tests pin that binding down.
 */
class QuadrantTest extends TestCase
{
    public function testPermittedValuesFollowClassInheritance(): void
    {
        $this->assertSame(
            ['EAST', 'NORTH', 'ORIGIN', 'SOUTH', 'WEST'],
            Quadrant::getPermittedValues()
        );

        $this->assertCount(9, Quadrant8::getPermittedValues());
        $this->assertContains('NORTHEAST', Quadrant8::getPermittedValues());
        $this->assertContains('NORTH', Quadrant8::getPermittedValues());

        // Without inheritance: only the four intermediate directions.
        $this->assertSame(
            ['NORTHEAST', 'NORTHWEST', 'SOUTHEAST', 'SOUTHWEST'],
            Quadrant8::getPermittedValues(false)
        );
    }

    public function testDefaultIsTheOrigin(): void
    {
        $this->assertSame(Quadrant::O, Quadrant::getDefault());
        $this->assertSame(Quadrant::O, Quadrant8::getDefault());
    }

    public function testThetaHalvesAtEachRefinement(): void
    {
        $this->assertEqualsWithDelta(M_PI / 2, Quadrant::getTheta(), 1e-9);
        $this->assertEqualsWithDelta(M_PI / 4, Quadrant8::getTheta(), 1e-9);
        $this->assertEqualsWithDelta(M_PI / 8, Quadrant16::getTheta(), 1e-9);
    }

    public function testRotations(): void
    {
        $this->assertNull(Quadrant::getRotation(Quadrant::O));
        $this->assertEqualsWithDelta(M_PI / 2, Quadrant::getRotation(Quadrant::N), 1e-9);
        $this->assertEqualsWithDelta(M_PI, Quadrant::getRotation(Quadrant::W), 1e-9);
        $this->assertEqualsWithDelta(3 * M_PI / 2, Quadrant::getRotation(Quadrant::S), 1e-9);
        $this->assertEqualsWithDelta(2 * M_PI, Quadrant::getRotation(Quadrant::E), 1e-9);
    }

    public function testUnknownQuadrantFallsBackToTheOrigin(): void
    {
        $this->assertNull(Quadrant::getRotation('not-a-quadrant'));
        $this->assertSame('center center', Quadrant::getPosition('not-a-quadrant'));
    }

    public function testRefinedRotationsDoNotRescaleTheParentDirections(): void
    {
        // NE gets the new, halved theta...
        $this->assertEqualsWithDelta(M_PI / 4, Quadrant8::getRotation(Quadrant8::NE), 1e-9);

        // ...while N inherited from Quadrant keeps the parent's theta.
        $this->assertEqualsWithDelta(M_PI / 2, Quadrant8::getRotation(Quadrant::N), 1e-9);
    }

    public function testPositions(): void
    {
        $this->assertSame('center center', Quadrant::getPosition(Quadrant::O));
        $this->assertSame('right center', Quadrant::getPosition(Quadrant::E));
        $this->assertSame('center top', Quadrant::getPosition(Quadrant::N));

        $this->assertSame('right top', Quadrant8::getPosition(Quadrant8::NE));
        $this->assertSame('center top', Quadrant8::getPosition(Quadrant::N), 'parent positions must be inherited');
    }

    public function testIconsMergeAcrossTheClassHierarchy(): void
    {
        $this->assertSame('fa-solid fa-chevron-up    fa-rotate-45', Quadrant8::getIcon(Quadrant8::NE));
        $this->assertSame('fa-solid fa-chevron-up', Quadrant8::getIcon(Quadrant::N), 'parent icons must be reachable from the child');
    }
}
