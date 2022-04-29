<?php

namespace Base\Enum\Quadrant;

use Base\Database\Type\EnumType;
use Base\Model\IconizeInterface;

class Quadrant extends EnumType implements IconizeInterface
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
            self::O   => ["fas fa-crosshairs"],
            self::E   => ["fas fa-chevron-right"],
            self::S   => ["fas fa-chevron-down"],
            self::W   => ["fas fa-chevron-left"],
            self::N   => ["fas fa-chevron-up"],
        ];
    }

    public static function getTheta() { return 2*pi()/4; }

    public static function getRotation(string $quadrant): ?float { return self::getRotations()[$quadrant] ?? self::getRotations()[self::O]; }
    public static function getRotations(): array
    {
        return [
            self::O   => null, 
            self::N   => 1 * self::getTheta(),
            self::W   => 2 * self::getTheta(),
            self::S   => 3 * self::getTheta(),
            self::E   => 4 * self::getTheta(),
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