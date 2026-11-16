<?php

namespace Tests\Base\Entity;

use Base\Contract\Environment;
use Base\Contract\Locales;
use Base\Contract\Runtime;
use Base\EntityDispatcher\Event\UserEvent;
use Base\EntitySubscriber\UserSubscriber;
use Base\Notifier\Notifier;
use Base\Service\Model\ClassAliases;
use Omnibridge\Contract\Access;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The Base\ / App\ convention where the core builds an entity: the App\ class, which is the host's own when it
 * declares one (a subclass of the Base\ one), Base\'s otherwise (ClassAliases). An entity of the core types its
 * relations with the App\ names (User::addToken(App\Entity\User\Token)): a Base\ instance built by the core is
 * refused on a host that has its own App\ class (lesringostars' App\Entity\User\Token, GoogleSignInTest).
 */
final class AppClassesAreBuiltTest extends TestCase
{
    /**
     * A host with its own App\Entity\User\Token: the verification token of a registration is one.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testARegistrationBuildsTheHostsOwnToken(): void
    {
        eval('namespace App\Entity\User; class Token extends \Base\Entity\User\Token {}');

        $token = $this->registrationToken();
        self::assertInstanceOf('App\Entity\User\Token', $token, 'the host\'s own class');
        self::assertNotSame(\Base\Entity\User\Token::class, get_class($token));
    }

    /**
     * A host without an App\Entity\User\Token: the alias App\ -> Base\ gives the core's own.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testARegistrationBuildsTheCoresTokenThroughTheAlias(): void
    {
        $token = $this->registrationToken();
        self::assertSame(\Base\Entity\User\Token::class, get_class($token), 'Base\'s, App\Entity\User\Token being its alias');
        self::assertInstanceOf('App\Entity\User\Token', $token);
    }

    /** The token UserSubscriber gives a new account that has its address to verify. */
    private function registrationToken(): object
    {
        ClassAliases::register();
        // An entity's constructor reads the runtime's locale (User::setLocale()): one, as a host gives it (no kernel here:
        // a separate process).
        if (!Runtime::has(Locales::class)) {
            Runtime::set(Locales::class, new class implements Locales {
                public function getLocale(): string { return 'en'; }
                public function getDefaultLocale(): string { return 'en'; }
                public function getAvailableLocales(): array { return ['en']; }
                public function getTimezone(): string { return 'UTC'; }
            });
        }
        // And its environment (User::isTester(), read when its token is given): a production one.
        if (!Runtime::has(Environment::class)) {
            Runtime::set(Environment::class, new class implements Environment {
                public function name(): string { return 'prod'; }
                public function isDebug(): bool { return false; }
                public function secret(): string { return 'test-secret'; }
            });
        }

        $user = new \App\Entity\User('anne@example.test');
        $event = $this->createMock(UserEvent::class);
        $event->method('getUser')->willReturn($user);
        $access = $this->createMock(Access::class);
        $access->method('getUser')->willReturn(null);
        $notifier = $this->createMock(Notifier::class);
        $notifier->method('verificationEmail')->willReturn(new class {
            public function send(): void
            {
            }
        });

        (new UserSubscriber($notifier, $access))->onRegistration($event);

        $tokens = $user->getTokens()->toArray();
        self::assertCount(1, $tokens);

        return reset($tokens);
    }

    /**
     * No code of the core builds an entity of the core by its Base\ name (new X() of an imported
     * Base\...\Entity\X, or new \Base\...\Entity\X()): it builds the App\ class. Base\Database\Entity holds
     * services, not entities.
     */
    public function testTheCoreBuildsNoEntityByItsBaseName(): void
    {
        $src = \dirname(__DIR__, 2).'/src';
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            $imported = [];
            preg_match_all('~^use\s+(Base\\\\(?!Database\\\\)(?:[A-Za-z]+\\\\)?Entity\\\\[A-Za-z\\\\]+?)(?:\s+as\s+(\w+))?;~m', $code, $uses, PREG_SET_ORDER);
            foreach ($uses as $use) {
                $imported[$use[2] ?? '' ?: substr(strrchr('\\'.$use[1], '\\'), 1)] = $use[1];
            }
            foreach (explode("\n", $code) as $number => $line) {
                if (preg_match('~new\s+\\\\?(Base\\\\(?!Database\\\\)(?:[A-Za-z]+\\\\)?Entity\\\\[A-Za-z\\\\]+)\s*\(~', $line, $match)) {
                    $found[] = substr($file->getPathname(), \strlen($src) + 1).':'.($number + 1).' '.$match[1];
                }
                if (preg_match_all('~new\s+(\w+)\s*\(~', $line, $matches)) {
                    foreach ($matches[1] as $short) {
                        if (isset($imported[$short])) {
                            $found[] = substr($file->getPathname(), \strlen($src) + 1).':'.($number + 1).' '.$imported[$short];
                        }
                    }
                }
            }
        }
        sort($found);

        self::assertSame([], $found);
    }
}
