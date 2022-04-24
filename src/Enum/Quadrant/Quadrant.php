<?php

namespace Base\Enum\Quadrant;

use Base\Database\Type\SetType;
use Base\Model\IconizeInterface;

class Quadrant extends SetType implements IconizeInterface
{
    const O    = "ORIGIN";

    const N    = "NORTH";
    const E    = "EAST";
    const S    = "SOUTH";
    const W    = "WEST";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::O   => ["fas fa-circle"],
            self::E   => ["fas fa-location-circle fa-45   "],
            self::S   => ["fas fa-location-circle fa-45   fa-flip-horizontal"],
            self::W   => ["fas fa-location-circle fa-22p5 fa-flip-horizontal fa-flip-vertical"],
            self::N   => ["fas fa-location-circle fa-45   fa-flip-vertical"],
        ];
    }

    public static function getTheta() { return 2*pi()/4; }

    public static function getRotation(string $quadrant): ?float { return self::getRotations()[$quadrant] ?? self::getRotations()[self::O]; }
    public static function getRotations(): array
    {
        return [
            self::O   => null, 
            self::N   => /* 4 **/ self::getTheta(),
            self::W   => /* 8 **/ self::getTheta(),
            self::S   => /*12 **/ self::getTheta(),
            self::E   => /*16 **/ self::getTheta(),
        ];
    }

    public static function getPosition(string $quadrant): string { return self::getPositions()[$quadrant] ?? self::getPositions()[self::O]; }
    public static function getPositions(): array
    {
        return [
            self::N   => "top",
            self::E   => "right",
            self::S   => "bottom",
            self::W   => "left",
        ];
    }
}