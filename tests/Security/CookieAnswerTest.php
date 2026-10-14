<?php

namespace Base\Tests\Security;

use Base\Security\CookieAnswer;
use PHPUnit\Framework\TestCase;

/** The visitor's answer about cookies, as the page's script keeps it, read on the server. */
class CookieAnswerTest extends TestCase
{
    public function testACategoryIsGrantedRefusedOrNotAnswered(): void
    {
        $cookies = ['cookie-consent' => '{"v":null,"t":1791940000,"c":{"USER":0,"LOCATION":1},"s":"save"}'];

        self::assertFalse(CookieAnswer::of('USER', $cookies));
        self::assertTrue(CookieAnswer::refused('user', $cookies), 'whatever its case');
        self::assertTrue(CookieAnswer::of('LOCATION', $cookies));
        self::assertFalse(CookieAnswer::refused('LOCATION', $cookies));
        self::assertNull(CookieAnswer::of('ANALYTICS', $cookies));
        self::assertFalse(CookieAnswer::refused('ANALYTICS', $cookies), 'not answered is not refused');
    }

    public function testNoAnswerWithoutTheCookieOrWithOneThatIsNotAnAnswer(): void
    {
        self::assertNull(CookieAnswer::of('USER', []));
        self::assertNull(CookieAnswer::of('USER', ['cookie-consent' => 'not json']));
        self::assertNull(CookieAnswer::of('USER', ['cookie-consent' => '{"c":"USER"}']));
        self::assertFalse(CookieAnswer::refused('USER', ['cookie-consent' => '[]']));
    }

    public function testPhpsOwnCookiesByDefault(): void
    {
        $kept = $_COOKIE;
        $_COOKIE = ['cookie-consent' => '{"c":{"USER":0}}'];
        try {
            self::assertTrue(CookieAnswer::refused('USER'));
        } finally {
            $_COOKIE = $kept;
        }
    }
}
