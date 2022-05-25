<?php

namespace Base\Component\Intl;

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\ResourceBundle;

final class Colors extends ResourceBundle
{
    public const COLOR_DIR = "colors";

    protected static function getPath(): string { dump(Intl::getDataDirectory()); return Intl::getDataDirectory().'/'.self::COLOR_DIR; }
    public static function getLanguageCodes(): array
    {
        return self::readEntry(['Colors'], 'meta');
    }

    public static function exists(string $color): bool
    {
        try {

            self::readEntry(['Names', $color]);
            return true;

        } catch (MissingResourceException $e) {
            return false;
        }
    }

    public static function getName(string $color, string $displayLocale = null): string
    {
        try {
            return self::readEntry(['Names', $color], $displayLocale);
        } catch (MissingResourceException $e) {
            throw $e;
        }
    }

    public static function getNames(string $displayLocale = null): array
    {
        return self::asort(self::readEntry(['Names'], $displayLocale), $displayLocale);
    }

    public static function getHSL(string $color): array { return hex2hsl(self::readEntry(['HexCode', $color], 'meta')); }
    public static function getRGB(string $color): array { return hex2rgb(self::readEntry(['HexCode', $color], 'meta')); }
    public static function getHexCode(string $color): string { return self::readEntry(['HexCode', $color], 'meta'); }
    public static function getHexCodes(): array { return self::readEntry(['HexCodes'], 'meta'); }
}
