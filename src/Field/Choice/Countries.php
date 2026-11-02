<?php

namespace Base\Field\Choice;

use Symfony\Component\Intl\Countries as IntlCountries;

/**
 * The countries a country field offers, neutral (symfony/intl only): their
 * names by code, the codes added by an application, those left out for want
 * of a flag. Base\Field\CountryField reads its choices here, so describing a
 * country field loads no form library; CountryType (Symfony) delegates here.
 */
final class Countries
{
    private static array $additionalList = [];
    private static array $rejectCountryList = [ // Rejected just because missing flag.. to do later
        "AO", "AI", "AQ", "AW", "BM", "CW", "GS", "GI", "GL",
        "GP", "GU", "GG", "GF", "BV", "CX", "IM", "NF", "AX",
        "KY", "CC", "CK", "FO", "HM", "FK", "MP", "UM", "PN",
        "TC", "VG", "VI", "JE", "RE", "MQ", "YT", "MS", "NU",
        "NC", "BQ", "PF", "MO", "EH", "BL", "MF", "SX", "PM",
        "SH", "AS", "SJ", "TF", "IO", "TK", "WF",
    ];

    // A way to add countries to the list.. (Another way is shown below using options)

    /**
     * @param $code
     * @param $country
     * @return void
     */
    public static function addCountry($code, $country)
    {
        self::addCountries([$code => $country]);
    }

    /**
     * @param $array
     * @return void
     */
    public static function addCountries($array)
    {
        $countryList = IntlCountries::getNames();
        foreach ($array as $code => $country) {
            if (array_key_exists($code, $countryList)) {
                throw new \RuntimeException("Country code \"$code\" ($country) already added in the true country list");
            }
            if (array_key_exists($code, self::$additionalList)) {
                throw new \RuntimeException("Country code \"$code\" ($country) already added in the fake country list");
            }

            self::$additionalList[$code] = $country;
        }
    }

    /**
     * @param string $code
     * @return mixed|string
     */
    public static function getName(string $code)
    {
        if (array_key_exists($code, self::$additionalList)) {
            return self::$additionalList[$code];
        }
        return IntlCountries::getName($code);
    }

    /**
     * @return array|string[]
     */
    public static function getNamesWithoutAddons()
    {
        return self::getNames(true);
    }

    /**
     * @param $addons
     * @return array|string[]
     */
    public static function getNames($addons = false)
    {
        $countryList = IntlCountries::getNames() + ($addons ? self::$additionalList : []);
        foreach (self::$rejectCountryList as $code) {
            unset($countryList[$code]);
        }

        return $countryList;
    }

    /** @return array<string, string> name => code */
    public static function getChoices(): array
    {
        return array_flip(self::getNames());
    }
}
