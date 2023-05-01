<?php

namespace Base\Database\Type;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;

/**
 *
 */
class UTCDateTimeType extends DateTimeType
{
    /**
     * @var DateTimeZone
     */
    private static $utc;

    /**
     * @param $value
     * @param AbstractPlatform $platform
     * @return mixed
     * @throws ConversionException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value instanceof DateTime) {
            $value->setTimezone(self::getUtc());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    /**
     * @param $value
     * @param AbstractPlatform $platform
     * @return mixed
     * @throws ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if (null === $value || $value instanceof DateTime) {
            return $value;
        }

        $converted = DateTime::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            self::getUtc()
        );

        if (!$converted) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                $platform->getDateTimeFormatString()
            );
        }

        return $converted;
    }

    private static function getUtc(): DateTimeZone
    {
        return self::$utc ?: self::$utc = new DateTimeZone('UTC');
    }
}
