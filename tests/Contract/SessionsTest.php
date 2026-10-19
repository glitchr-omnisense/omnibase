<?php

namespace Tests\Base\Contract;

use Omnibridge\Symfony\Contract\SecuritySessions;
use Base\Contract\Runtime;
use Base\Service\Localizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The last of User's reads through BaseTrait (plan "omnibase agnostique",
 * C4b), under Symfony: logout() ends the session through the Sessions
 * contract as it did through the token storage (the visitor signed out, a
 * user who is not the visitor kicked), and getLocale()/setLocale() without a
 * locale of the user's own give the page's locale through the Locales
 * contract, what Localizer::__toLocale() of the translator's locale gave for
 * every language the site speaks.
 */
final class SessionsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get('security.token_storage')->setToken(null);
        parent::tearDown();
    }

    private static function user(int $id): object
    {
        $user = (new \ReflectionClass('App\\Entity\\User'))->newInstanceWithoutConstructor();
        (new \ReflectionProperty('Base\\Entity\\User', 'id'))->setValue($user, $id);
        $user->setEmail('c4b-'.$id.'@example.org');
        $user->setIsKicked(false);

        return $user;
    }

    public function testLogoutSignsTheVisitorOutAndKicksAnyoneElse(): void
    {
        self::assertInstanceOf(SecuritySessions::class, Runtime::sessions());
        $tokens = static::getContainer()->get('security.token_storage');

        // Nobody signed in: the user is kicked out of its sessions elsewhere.
        $absent = self::user(1);
        $absent->logout();
        self::assertTrue($absent->isKicked());

        // Someone else signed in: kicked, the visitor's session untouched.
        $visitor = self::user(2);
        $tokens->setToken(new UsernamePasswordToken($visitor, 'main', ['ROLE_USER']));
        $other = self::user(3);
        $other->logout();
        self::assertTrue($other->isKicked());
        self::assertSame($visitor, $tokens->getToken()?->getUser());

        // The visitor: signed out (the token forgotten; the cookies cleared, headers already sent here), not kicked.
        @$visitor->logout();
        self::assertNull($tokens->getToken());
        self::assertFalse($visitor->isKicked());
    }

    public function testAUsersLocaleIsThePagesWithoutItsOwn(): void
    {
        $translator = static::getContainer()->get('Base\Service\Translator');
        $locales = array_values(array_unique(array_merge(
            Localizer::getAvailableLocales(),
            array_map(fn ($l) => str_replace('-', '_', $l), Localizer::getAvailableLocales()),
            array_map(fn ($l) => substr($l, 0, 2), Localizer::getAvailableLocales()),
        )));
        self::assertGreaterThan(2, count($locales));

        foreach ($locales as $locale) {
            $translator->setLocale($locale);
            $user = self::user(4);
            self::assertSame(Localizer::__toLocale($translator->getLocale()), $user->getLocale(), $locale);
            self::assertSame(Localizer::__toLocale($translator->getLocale()), $user->setLocale()->getLocale(), $locale.', set');
            self::assertSame('ja-JP', $user->setLocale('ja-JP')->getLocale(), 'its own locale first');
        }
    }
}
