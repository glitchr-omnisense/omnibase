<?php

namespace Base\Enum\Quadrant;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;

class Quadrant extends EnumType implements IconizeInterface
{
    public const O    = "ORIGIN";

    public const N    = "NORTH";
    public const E    = "EAST";
    public const S    = "SOUTH";
    public const W    = "WEST";

    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::O   => ["fa-solid fa-crosshairs"],
            self::E   => ["fa-solid fa-chevron-right"],
            self::S   => ["fa-solid fa-chevron-down"],
            self::W   => ["fa-solid fa-chevron-left"],
            self::N   => ["fa-solid fa-chevron-up"],
        ];
    }

    public static function getDefault()
    {
        return self::O;
    }
    public static function getTheta()
    {
        return 2*pi()/4;
    }

    public static function getRotation(string $quadrant): ?float
    {
        return self::getRotations()[$quadrant] ?? self::getRotations()[self::O];
    }
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

    public static function getPosition(string $quadrant): string
    {
        return self::getPositions()[$quadrant] ?? self::getPositions()[self::O];
    }
    public static function getPositions(): array
    {
        return [
            self::O   => "center center",
            self::N   => "center top",
            self::S   => "center bottom",
            self::E   => "right center",
            self::W   => "left center",
        ];
    }
}
