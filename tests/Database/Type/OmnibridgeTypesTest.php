<?php

namespace Tests\Base\Database\Type;

use Base\Database\Type\DateTimeTypeUTC;
use Base\Database\Type\Utc;
use Base\Database\Type\UtcDateTimeImmutableType;
use Base\Database\Type\UtcDateTimeType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Omnibridge\Doctrine\Type as Bridge;
use PHPUnit\Framework\TestCase;

/**
 * omnibase's moment types are omnibridge/doctrine's: datetime (DateTimeTypeUTC
 * extends its DateTimeType), utc_datetime and utc_datetime_immutable (its
 * classes are final: delegated to), the same zone. Each pair writes the same
 * text and reads back the same moment, from Tokyo, Paris and UTC, so a site
 * mapped by omnibase and an application mapped by omnibridge/doctrine
 * (Omnibridge\Doctrine\Types::register()) read each other's rows.
 */
final class OmnibridgeTypesTest extends TestCase
{
    private string $zone;

    protected function setUp(): void
    {
        $this->zone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
    }

    /** @return iterable<string, array{Type, Type}> */
    public static function pairs(): iterable
    {
        yield 'datetime' => [new DateTimeTypeUTC(), new Bridge\DateTimeType()];
        yield 'utc_datetime' => [new UtcDateTimeType(), new Bridge\UtcDateTimeType()];
        yield 'utc_datetime_immutable' => [new UtcDateTimeImmutableType(), new Bridge\UtcDateTimeImmutableType()];
    }

    /** @dataProvider pairs */
    #[\PHPUnit\Framework\Attributes\DataProvider('pairs')]
    public function testOmnibaseAndOmnibridgeConvertAlike(Type $omnibase, Type $omnibridge): void
    {
        $platform = new SQLitePlatform();
        $immutable = str_contains($omnibase::class, 'Immutable');
        foreach (['Asia/Tokyo', 'Europe/Paris', 'UTC'] as $zone) {
            date_default_timezone_set($zone);
            foreach (['2026-10-17 21:30:00', '2022-03-01 00:00:00'] as $wall) {
                $make = static fn () => $immutable ? new \DateTimeImmutable($wall) : new \DateTime($wall);
                $written = $omnibase->convertToDatabaseValue($make(), $platform);
                self::assertSame($omnibridge->convertToDatabaseValue($make(), $platform), $written, $zone.' '.$wall);

                $read = $omnibase->convertToPHPValue($written, $platform);
                $bridged = $omnibridge->convertToPHPValue($written, $platform);
                self::assertSame($bridged->format(\DATE_ATOM), $read->format(\DATE_ATOM));
                self::assertSame('UTC', $read->getTimezone()->getName());
                self::assertSame($make()->getTimestamp(), $read->getTimestamp());
            }
            self::assertNull($omnibase->convertToDatabaseValue(null, $platform));
            self::assertNull($omnibase->convertToPHPValue(null, $platform));
        }

        // Unreadable, both refuse it alike. (`datetime`: omnibridge/doctrine's DateTimeType
        // calls DBAL 3's ConversionException::conversionFailedFormat(), an \Error on DBAL 4 -
        // as omnibase's DateTimeTypeUTC did before it, with $this->getName() besides.)
        $refusals = [];
        foreach ([$omnibase, $omnibridge] as $type) {
            try {
                $type->convertToPHPValue('not a date', $platform);
                $refusals[] = null;
            } catch (\Throwable $e) {
                $refusals[] = $e::class;
            }
        }
        self::assertNotNull($refusals[0]);
        self::assertSame($refusals[1], $refusals[0]);
        if (!str_contains($omnibase::class, 'DateTimeTypeUTC')) {
            self::assertTrue(is_a($refusals[0], ConversionException::class, true), $refusals[0]);
        }
    }

    public function testTheSameZoneAndTheNamesKept(): void
    {
        self::assertSame(Bridge\Utc::zone(), Utc::zone());
        self::assertSame('utc_datetime', UtcDateTimeType::NAME);
        self::assertSame('utc_datetime_immutable', UtcDateTimeImmutableType::NAME);
        self::assertInstanceOf(Bridge\DateTimeType::class, new DateTimeTypeUTC());
    }
}
