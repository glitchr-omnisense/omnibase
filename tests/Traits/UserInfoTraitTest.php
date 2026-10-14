<?php

namespace Base\Tests\Traits;

use Base\Traits\UserInfoTrait;
use PHPUnit\Framework\TestCase;

/**
 * What the server reads of the cookies the page's script writes
 * (assets/styles/js/user.js): the time zone from LOCATION/TIMEZONE where the
 * visitor answers for it apart, from USER/INFO otherwise; the country from
 * the zone, without writing anything back.
 */
class UserInfoTraitTest extends TestCase
{
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->cookies = $_COOKIE;
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookies;
    }

    private function user(): object
    {
        return new class {
            use UserInfoTrait;
        };
    }

    public function testTheZoneComesWithTheRestWhereItHasNoCookieOfItsOwn(): void
    {
        $_COOKIE['USER/INFO'] = '{"timezone":"Europe/Paris","locale":"fr"}';

        self::assertSame('Europe/Paris', $this->user()::getCookie('timezone'));
        self::assertSame('FR', $this->user()::getCookie('country'));
        self::assertSame('fr', $this->user()::getCookie('locale'));
        self::assertSame(['timezone' => 'Europe/Paris', 'locale' => 'fr', 'country' => 'FR'], $this->user()::getCookie());
    }

    public function testTheZonesOwnCookieIsRead(): void
    {
        $_COOKIE['USER/INFO'] = '{"locale":"fr"}';
        $_COOKIE['LOCATION/TIMEZONE'] = 'Asia/Tokyo';

        self::assertSame('Asia/Tokyo', $this->user()::getCookie('timezone'));
        self::assertSame('JP', $this->user()::getCookie('country'));
    }

    public function testTheZoneAloneWhenTheRestIsRefused(): void
    {
        $_COOKIE['LOCATION/TIMEZONE'] = 'Europe/Berlin';

        self::assertSame('Europe/Berlin', $this->user()::getCookie('timezone'));
        self::assertSame('DE', $this->user()::getCookie('country'));
        self::assertNull($this->user()::getCookie('locale'));
    }

    public function testNothingWithoutACookieNorForAZoneWithoutACountry(): void
    {
        self::assertNull($this->user()::getCookie('timezone'));
        self::assertNull($this->user()::getCookie('country'));
        self::assertSame([], $this->user()::getCookie());

        $_COOKIE['LOCATION/TIMEZONE'] = 'UTC';
        self::assertSame('UTC', $this->user()::getCookie('timezone'));
        self::assertNull($this->user()::getCookie('country'), 'UTC is nobody\'s country, and no exception');

        $_COOKIE['USER/INFO'] = 'not json';
        self::assertSame('UTC', $this->user()::getCookie('timezone'));
    }
}
