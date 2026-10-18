<?php

namespace Tests\Base\Database\Type;

use Base\Service\Model\IconizeInterface;
use PHPUnit\Framework\TestCase;

class EnumColumnTestRoles extends \Base\Database\Type\SetType implements IconizeInterface
{
    public const USER = 'ROLE_USER';
    public const ADMIN = 'ROLE_ADMIN';

    public function __iconize(): ?array { return null; }

    public static function __iconizeStatic(): ?array
    {
        return [self::USER => ['fa-solid fa-user'], self::ADMIN => ['fa-solid fa-crown']];
    }
}

/** An application's roles: the core's plus its own, the icons of all of them named in one place. */
class EnumColumnTestAppRoles extends EnumColumnTestRoles implements IconizeInterface
{
    public const PRO = 'ROLE_PRO';

    public static function __iconizeStatic(): ?array
    {
        return parent::__iconizeStatic() + [self::PRO => ['fa-solid fa-feather']];
    }
}

class EnumColumnTestTypo extends EnumColumnTestRoles implements IconizeInterface
{
    public const PRO = 'ROLE_PRO';

    public static function __iconizeStatic(): ?array
    {
        return ['ROLE_PROO' => ['fa-solid fa-feather']];
    }
}

/**
 * An enumeration that extends another may name the icons of the values it
 * inherits. (A SET or ENUM column's values compared with the database, which
 * this test also checked through the "(DC2Type:...)" comment EnumSubscriber
 * wrote: tests/Bridge/Doctrine/SchemaManagerTest, since doctrine/dbal is
 * used as published - plan "omnibase agnostique", C6b.)
 */
final class EnumColumnTest extends TestCase
{
    public function testAnEnumerationMayNameTheIconsOfTheValuesItInherits(): void
    {
        $icons = EnumColumnTestAppRoles::getIcons();
        self::assertArrayHasKey('ROLE_PRO', $icons);
        self::assertArrayHasKey('ROLE_ADMIN', $icons);
    }

    public function testAnIconForNoValueIsStillAnError(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        EnumColumnTestTypo::getIcons();
    }
}
