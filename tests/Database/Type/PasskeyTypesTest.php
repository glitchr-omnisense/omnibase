<?php

namespace Tests\Base\Database\Type;

use Base\Bridge\Doctrine\Types;
use Base\Database\Type\Passkey\Aaguid;
use Base\Database\Type\Passkey\Base64;
use Base\Database\Type\Passkey\TrustPath;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\Bundle\Doctrine\Type\AAGUIDDataType;
use Webauthn\Bundle\Doctrine\Type\Base64BinaryDataType;
use Webauthn\Bundle\Doctrine\Type\TrustPathDataType;
use Webauthn\Denormalizer\TrustPathDenormalizer;
use Webauthn\TrustPath\CertificateTrustPath;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * The passkey's columns described by the core (plan "omniauth", AU1):
 * Base\Database\Type\Passkey\{Base64, Aaguid, TrustPath} declare the SQL of
 * web-auth/webauthn-symfony-bundle's `base64`, `aaguid` and `trust_path`
 * (the types the userPasskey table was made with), and a value written by
 * either is read by the other - so the table needs no migration and the rows
 * already written stay readable. The bundle is in the core's require-dev for
 * this comparison.
 */
final class PasskeyTypesTest extends TestCase
{
    /** @return iterable<string, array{AbstractPlatform}> */
    public static function platforms(): iterable
    {
        yield 'MySQL' => [new MySQL80Platform()];
        yield 'SQLite' => [new SQLitePlatform()];
        yield 'PostgreSQL' => [new PostgreSQLPlatform()];
    }

    /** @dataProvider platforms */
    public function testTheSqlDeclarationsAreTheBundles(AbstractPlatform $platform): void
    {
        foreach ([
            [new Base64(), new Base64BinaryDataType(), ['name' => 'publicKeyCredentialId']],
            [new Aaguid(), new AAGUIDDataType(), ['name' => 'aaguid', 'length' => 36]],
            [new TrustPath(), new TrustPathDataType(), ['name' => 'trustPath']],
        ] as [$core, $bundle, $column]) {
            self::assertSame($bundle->getSQLDeclaration($column, $platform), $core->getSQLDeclaration($column, $platform), $core::class);
        }
        if ($platform instanceof MySQL80Platform) {
            self::assertSame('LONGTEXT', (new Base64())->getSQLDeclaration([], $platform));
            self::assertSame('TINYTEXT', (new Aaguid())->getSQLDeclaration(['length' => 36], $platform));
            self::assertSame('JSON', (new TrustPath())->getSQLDeclaration([], $platform));
        }
    }

    public function testBytesAreWrittenAndReadAsTheBundleDoes(): void
    {
        $platform = new MySQL80Platform();
        $core = new Base64();
        $bundle = new Base64BinaryDataType();

        foreach ([random_bytes(32), random_bytes(77), "\0\xff/+=", ''] as $bytes) {
            $text = $bundle->convertToDatabaseValue($bytes, $platform);
            self::assertSame($text, $core->convertToDatabaseValue($bytes, $platform), 'the same text');
            self::assertSame($bytes, $core->convertToPHPValue($text, $platform), 'the bundle\'s text read here');
            self::assertSame($bytes, $bundle->convertToPHPValue($core->convertToDatabaseValue($bytes, $platform), $platform), 'this text read by the bundle');
        }
        self::assertNull($core->convertToDatabaseValue(null, $platform));
        self::assertNull($core->convertToPHPValue(null, $platform));

        $this->expectException(ValueNotConvertible::class);
        $core->convertToPHPValue('not base64!', $platform);
    }

    public function testTheAaguidIsTheTextOfTheBundlesUuid(): void
    {
        $platform = new MySQL80Platform();
        $core = new Aaguid();
        $bundle = new AAGUIDDataType();

        foreach (['00000000-0000-0000-0000-000000000000', 'adce0002-35bc-c60a-648b-0b25f1f05503', (string) Uuid::v4()] as $text) {
            $uuid = Uuid::fromString($text);
            self::assertSame($bundle->convertToDatabaseValue($uuid, $platform), $core->convertToDatabaseValue($text, $platform));
            self::assertSame($bundle->convertToDatabaseValue($uuid, $platform), $core->convertToDatabaseValue($uuid, $platform), 'a Uuid given is written as its text');
            self::assertSame($text, $core->convertToPHPValue($bundle->convertToDatabaseValue($uuid, $platform), $platform));
            self::assertTrue($uuid->equals($bundle->convertToPHPValue($core->convertToDatabaseValue($text, $platform), $platform)));
        }
    }

    public function testTheTrustPathIsTheBundlesJson(): void
    {
        $platform = new MySQL80Platform();
        $core = new TrustPath();
        $bundle = new TrustPathDataType();
        $normalizer = new TrustPathDenormalizer();

        foreach ([EmptyTrustPath::create(), CertificateTrustPath::create(['MIIBkTCB+wIJAKHBfpegPjMCMA0GCSqGSIb3DQEBCwUAMBExDzANBgNVBAMMBnRlc3RDQTAeFw0x/w==', 'MIIB'])] as $trustPath) {
            $json = $bundle->convertToDatabaseValue($trustPath, $platform);
            $array = $normalizer->normalize($trustPath);
            self::assertSame($json, $core->convertToDatabaseValue($array, $platform), 'the same JSON, slashes escaped alike');
            self::assertSame($array, $core->convertToPHPValue($json, $platform));
            self::assertEquals($trustPath, $bundle->convertToPHPValue($core->convertToDatabaseValue($array, $platform), $platform));
        }
        self::assertSame('[]', $core->convertToDatabaseValue([], $platform));
    }

    public function testTheyAreTheCoresTypesByName(): void
    {
        self::assertSame([
            'passkey_base64' => Base64::class,
            'passkey_aaguid' => Aaguid::class,
            'passkey_trust_path' => TrustPath::class,
        ], Types::passkey());
        self::assertSame(Types::passkey(), array_intersect_key(Types::core(), Types::passkey()));

        Types::register();
        foreach (Types::passkey() as $name => $class) {
            self::assertInstanceOf($class, Type::getType($name));
        }
    }
}
