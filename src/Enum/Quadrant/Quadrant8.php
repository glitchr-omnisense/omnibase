<?php

namespace Base\Enum\Quadrant;

use Base\Service\Model\IconizeInterface;

class Quadrant8 extends Quadrant
{
    public const NE   = "NORTHEAST";
    public const SE   = "SOUTHEAST";
    public const SW   = "SOUTHWEST";
    public const NW   = "NORTHWEST";

    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::NE => ["fa-solid fa-chevron-up    fa-rotate-45"],
            self::SE => ["fa-solid fa-chevron-right fa-rotate-45"],
            self::SW => ["fa-solid fa-chevron-down  fa-rotate-45"],
            self::NW => ["fa-solid fa-chevron-left  fa-rotate-45"],
        ];
    }

    public static function getTheta()
    {
        return parent::getTheta()/2;
    }

    public static function getRotation(string $quadrant): ?float
    {
        return self::getRotations()[$quadrant] ?? self::getRotations()[self::O];
    }
    public static function getRotations(): array
    {
        return array_merge(parent::getRotations(), [

            self::NE  =>  1 * self::getTheta(),
            self::NW  =>  3 * self::getTheta(),
            self::SW  =>  5 * self::getTheta(),
            self::SE  =>  7 * self::getTheta(),
        ]);
    }

    public static function getPosition(string $quadrant): string
    {
        return self::getPositions()[$quadrant] ?? self::getPositions()[self::O];
    }
    public static function getPositions(): array
    {
        return array_merge(parent::getPositions(), [
            self::NE  => "right top",
            self::SE  => "right bottom",
            self::SW  => "left bottom",
            self::NW  => "left top",
        ]);
    }
}
