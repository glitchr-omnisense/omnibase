<?php

namespace Base\Enum\Quadrant;

use Base\Model\IconizeInterface;

class Quadrant32 extends Quadrant16 implements IconizeInterface
{
    const NbW  = "NORTH_BY_WEST";
    const NbE  = "NORTH_BY_EAST";
    const NEbN = "NORTHEAST_BY_EAST";
    const NEbE = "NORTHEAST_BY_EAST";
    const EbN  = "EAST_BY_NORTH";
    const EbS  = "EAST_BY_SOUTH";
    const SEbE = "SOUTHEAST_BY_EAST";
    const SEbS = "SOUTHEAST_BY_SOUTH";
    const SbE  = "SOUTH_BY_EAST";
    const SbW  = "SOUTH_BY_WEST";
    const SWbS = "SOUTHWEST_BY_SOUTH";
    const SWbW = "SOUTHWEST_BY_WEST";
    const WbS  = "WEST_BY_SOUTH";
    const WbN  = "WEST_BY_NORTH";
    const NWbW = "NORTHWEST_BY_WEST";
    const NWbN = "NORTHWEST_BY_NORTH";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return array_merge(parent::__iconizeStatic(), [

            self::NbW  => ["fas fa-location-circle"],
            self::NbE  => ["fas fa-location-circle"],
            self::NEbN => ["fas fa-location-circle"],
            self::NEbE => ["fas fa-location-circle"],
            self::EbN  => ["fas fa-location-circle"],
            self::EbS  => ["fas fa-location-circle"],
            self::SEbE => ["fas fa-location-circle"],
            self::SEbS => ["fas fa-location-circle"],
            self::SbE  => ["fas fa-location-circle"],
            self::SbW  => ["fas fa-location-circle"],
            self::SWbS => ["fas fa-location-circle"],
            self::SWbW => ["fas fa-location-circle"],
            self::WbS  => ["fas fa-location-circle"],
            self::WbN  => ["fas fa-location-circle"],
            self::NWbW => ["fas fa-location-circle"],
            self::NWbN => ["fas fa-location-circle"],
        ]);
    }

    public static function getTheta() { return parent::getTheta()/2; }

    public static function getRotation(string $quadrant): ?float { return self::getRotations()[$quadrant] ?? self::getRotations()[self::O]; }
    public static function getRotations(): array
    {
        return array_merge(parent::getRotations(), [

            self::NbW  => /* 1 * */ self::getTheta(),
            self::NbE  => /* 1 * */ self::getTheta(),
            self::NEbN => /* 1 * */ self::getTheta(),
            self::NEbE => /* 1 * */ self::getTheta(),
            self::EbN  => /* 1 * */ self::getTheta(),
            self::EbS  => /* 1 * */ self::getTheta(),
            self::SEbE => /* 1 * */ self::getTheta(),
            self::SEbS => /* 1 * */ self::getTheta(),
            self::SbE  => /* 1 * */ self::getTheta(),
            self::SbW  => /* 1 * */ self::getTheta(),
            self::SWbS => /* 1 * */ self::getTheta(),
            self::SWbW => /* 1 * */ self::getTheta(),
            self::WbS  => /* 1 * */ self::getTheta(),
            self::WbN  => /* 1 * */ self::getTheta(),
            self::NWbW => /* 1 * */ self::getTheta(),
            self::NWbN => /* 1 * */ self::getTheta(),
        ]);
    }

    public static function getPosition(string $quadrant): string { return self::getPositions()[$quadrant] ?? self::getPositions()[self::O]; }
    public static function getPositions(): array
    {
        return array_merge(parent::getPositions(), [

            self::NbW  => "",
            self::NbE  => "",
            self::NEbN => "",
            self::NEbE => "",
            self::EbN  => "",
            self::EbS  => "",
            self::SEbE => "",
            self::SEbS => "",
            self::SbE  => "",
            self::SbW  => "",
            self::SWbS => "",
            self::SWbW => "",
            self::WbS  => "",
            self::WbN  => "",
            self::NWbW => "",
            self::NWbN => "",
        ]);
    }
}