<?php

namespace Base\Enum\Quadrant;

use Base\Model\IconizeInterface;

class Quadrant8 extends Quadrant implements IconizeInterface
{
    const NE   = "NORTHEAST";
    const SE   = "SOUTHEAST";
    const SW   = "SOUTHWEST";
    const NW   = "NORTHWEST";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return array_merge(parent::__iconizeStatic(), [
            self::NE => ["fas fa-location-circle         "],
            self::SE => ["fas fa-location-circle         fa-flip-horizontal"],
            self::SW => ["fas fa-location-circle         fa-flip-horizontal fa-flip-vertical"],
            self::NW => ["fas fa-location-circle fa-67p5 fa-flip-vertical"],
        ]);
    }

    public static function getTheta() { return parent::getTheta()/2; }

    public static function getRotation(string $quadrant): ?float { return self::getRotations()[$quadrant] ?? self::getRotations()[self::O]; }
    public static function getRotations(): array
    {
        $theta = 180/8;
        return array_merge(parent::getRotations(), [

            self::NE  => /* 2 **/ self::getTheta(),
            self::NW  => /* 6 **/ self::getTheta(),
            self::SW  => /*10 **/ self::getTheta(),
            self::SE  => /*14 **/ self::getTheta(),
        ]);
    }

    public static function getPosition(string $quadrant): string { return self::getPositions()[$quadrant] ?? self::getPositions()[self::O]; }
    public static function getPositions(): array
    {
        return array_merge(parent::getPositions(), [
            self::NE  => "top    right",
            self::SE  => "right  bottom",
            self::SW  => "bottom left",
            self::NW  => "left   top",
        ]);
    }
}