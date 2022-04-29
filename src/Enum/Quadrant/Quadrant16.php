<?php

namespace Base\Enum\Quadrant;

use Base\Model\IconizeInterface;

class Quadrant16 extends Quadrant8 implements IconizeInterface
{
    const NNE  = "NORTH_NORTHEAST";
    const ENE  = "EAST_NORTHEAST";
    const ESE  = "EAST_SOUTHEAST";
    const SSE  = "SOUTH_SOUTHEAST";
    const SSW  = "SOUTH_SOUTHWEST";
    const WSW  = "WEST_SOUTHWEST";
    const WNW  = "WEST_NORTHWEST";
    const NNW  = "NORTH_NORTHWEST";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return array_merge(parent::__iconizeStatic(), [
            self::ENE => ["fas fa-location-arrow fa-22p5 "],
            self::ESE => ["fas fa-location-arrow fa-67p5 "],
            self::SSE => ["fas fa-location-arrow fa-22p5 fa-flip-horizontal"],
            self::SSW => ["fas fa-location-arrow fa-67p5 fa-flip-horizontal"],
            self::WSW => ["fas fa-location-arrow fa-67p5 fa-flip-horizontal fa-flip-vertical"],
            self::WNW => ["fas fa-location-arrow fa-45   fa-flip-horizontal fa-flip-vertical"],
            self::NNW => ["fas fa-location-arrow fa-22p5 fa-flip-vertical"],
            self::NNE => ["fas fa-location-arrow fa-67p5 fa-flip-vertical"],
        ]);
    }

    public static function getTheta() { return parent::getTheta()/2; }

    public static function getRotation(string $quadrant): ?float { return self::getRotations()[$quadrant] ?? self::getRotations()[self::O]; }
    public static function getRotations(): array
    {
        return array_merge(parent::getRotations(), [
            self::ENE =>  1 * self::getTheta(),
            self::NNE =>  3 * self::getTheta(),
            self::NNW =>  5 * self::getTheta(),
            self::WNW =>  7 * self::getTheta(),
            self::WSW =>  9 * self::getTheta(),
            self::SSW => 11 * self::getTheta(),
            self::SSE => 13 * self::getTheta(),
            self::ESE => 15 * self::getTheta(),
        ]);
    }

    public static function getPosition(string $quadrant): string { return self::getPositions()[$quadrant] ?? self::getPositions()[self::O]; }
    public static function getPositions(): array
    {
        return array_merge(parent::getPositions(), [
            self::NNE => "top    75% right  25%",
            self::ENE => "top    25% right  75%",
            self::ESE => "right  75% bottom 25%",
            self::SSE => "right  25% bottom 75%",
            self::SSW => "bottom 75% left   25%",
            self::WSW => "bottom 25% left   75%",
            self::WNW => "left   75% top    25%",
            self::NNW => "left   25% top    75%",
        ]);
    }
}