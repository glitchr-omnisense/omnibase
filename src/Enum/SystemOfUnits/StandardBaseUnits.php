<?php

namespace Base\Enum\SystemOfUnits;

use Base\Database\Type\EnumType;
use Base\Model\ColorizeInterface;
use Base\Model\IconizeInterface;

class StandardBaseUnits extends EnumType implements IconizeInterface, ColorizeInterface
{
    const SECOND   = "s";
    const METER    = "m";
    const KILOGRAM = "kg";
    const AMPERE   = "A";
    const KELVIN   = "K";
    const MOLE     = "mol";
    const CANDELA  = "cd";

    public function __iconize(): ?array { return null; }
    public static function __iconizeStatic(): ?array
    {
        return [
            self::SECOND   => ["fas fa-question-circle"],
            self::METER    => ["fas fa-question-circle"],
            self::KILOGRAM => ["fas fa-question-circle"],
            self::AMPERE   => ["fas fa-question-circle"],
            self::KELVIN   => ["fas fa-question-circle"],
            self::MOLE     => ["fas fa-question-circle"],
            self::CANDELA  => ["fas fa-question-circle"],
        ];
    }

    public function __colorize(): ?array { return null; }
    public static function __colorizeStatic(): ?array { 

        return [
            self::SECOND   => "#f5a801",
            self::METER    => "#ff671c",
            self::KILOGRAM => "#ce0d2d",
            self::AMPERE   => "#61a60e",
            self::KELVIN   => "#005db9",
            self::MOLE     => "#c017a2",
            self::CANDELA  => "#410099"
        ];
    }

    public static function getBaseUnit(string $name): ?string { return self::getBaseUnits()[$name] ?? null; }
    public static function getBaseUnits(): ?array { return parent::getPermittedValues(); }

    public static function getBaseUnitName(string $name): ?string { return self::getBaseUnitNames()[$name] ?? null; }
    public static function getBaseUnitNames(): ?array
    {
        return [
            self::SECOND   => "time",
            self::METER    => "length",
            self::KILOGRAM => "mass",
            self::AMPERE   => "electric current",
            self::KELVIN   => "kelvin",
            self::MOLE     => "amount of substance",
            self::CANDELA  => "luminous intensity",
        ];
    }

    public static function getConstant(string $name): ?string { return self::getConstants()[$name] ?? null; }
    public static function getConstants(): ?array
    {
        return [
            self::SECOND   => 9192631770,
            self::METER    => 299792458,
            self::KILOGRAM => 6.62607015e-34,
            self::AMPERE   => 1.602176634e-19,
            self::KELVIN   => 1.380649e-23,
            self::MOLE     => 6.02214076e+23,
            self::CANDELA  => 683
        ];
    }
    
    public static function getConstantSymbol(string $name): ?string { return self::getConstantSymbols()[$name] ?? null; }
    public static function getConstantSymbols(): ?array
    {
        return [
            self::SECOND   => '$\Delta\nu_{Cs}$',
            self::METER    => "c",
            self::KILOGRAM => "h",
            self::AMPERE   => "e",
            self::KELVIN   => "k",
            self::MOLE     => "N_A",
            self::CANDELA  => '$K_{cd}$'
        ];
    }

    public static function getConstantUnit(string $name): ?string { return self::getConstantUnits()[$name] ?? null; }
    public static function getConstantUnits(): ?array
    {
        return [
            self::SECOND   => "Hz",
            self::METER    => "m/s",
            self::KILOGRAM => "J.s",
            self::AMPERE   => "C",
            self::KELVIN   => "J/K",
            self::MOLE     => "mol^-1",
            self::CANDELA  => "lm/W",
        ];
    }

    public static function getConstantName(string $name): ?string { return self::getConstantNames()[$name] ?? null; }
    public static function getConstantNames(): ?array
    {
        return [
            self::SECOND   => "hyperfine transition frequency of Cs",
            self::METER    => "speed of light",
            self::KILOGRAM => "Planck constant",
            self::AMPERE   => "elementary charge",
            self::KELVIN   => "Boltzmann constant",
            self::MOLE     => "Avogadro constant",
            self::CANDELA  => "luminous efficacy of 540 THz radiation",
        ];
    }
}