<?php

namespace Tests\Base\Contract;

use Omnibridge\Symfony\Contract\KernelEnvironment;
use Base\Contract\Runtime;
use Base\Entity\Layout\SettingIntl;
use Base\Entity\User\Token;
use Base\Service\BaseService;
use Hashids\Hashids;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What the application runs as and its parameters, moved off BaseTrait and
 * BaseService onto the Environment contract and Runtime::parameters() (plan
 * "omnibase agnostique", C4b), under Symfony: the environment's name, debug
 * and secret are BaseService's getEnvironment(), isDebug() and getSalt(); the
 * parameters are the same `parameter_bag` (the settings stored in the
 * database over the container's); User's delays and tester's check, Token's
 * default throttling and hash salt, a secured entity's vault answer as their
 * old code did.
 */
final class EnvironmentTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheEnvironmentIsTheKernels(): void
    {
        $kernel = static::$kernel;
        self::assertInstanceOf(KernelEnvironment::class, Runtime::environment());
        self::assertSame($kernel->getEnvironment(), Runtime::environment()->name());
        self::assertSame(BaseService::getEnvironment(), Runtime::environment()->name());
        self::assertSame($kernel->isDebug(), Runtime::environment()->isDebug());
        self::assertSame(BaseService::getService()->isDebug(), Runtime::environment()->isDebug());
        self::assertSame(BaseService::getService()->getSalt(), Runtime::environment()->secret());
        self::assertNotSame('', Runtime::environment()->secret());
    }

    public function testTheParametersAreBaseTraitsParameterBag(): void
    {
        self::assertSame(static::getContainer()->get('parameter_bag'), Runtime::parameters());
        self::assertSame(BaseService::getParameterBag(), Runtime::parameters(), 'the bag with the settings stored in the database');
        foreach (['base.user.active_delay', 'base.user.online_delay', 'base.user.token_default_throttling', 'base.notifier.test_recipients', 'kernel.secret'] as $key) {
            self::assertSame(BaseService::getParameterBag($key), Runtime::parameters()->get($key), $key);
        }
    }

    public function testUsersTokensAndVaultsAnswerAsBefore(): void
    {
        $userClass = 'App\\Entity\\User';
        self::assertSame(BaseService::getParameterBag()->get('base.user.active_delay'), $userClass::getActiveDelay());
        self::assertSame(BaseService::getParameterBag()->get('base.user.online_delay'), $userClass::getOnlineDelay());

        // isTester(): debug, then an address among the test recipients (the old code, as it was).
        $recipients = BaseService::getService()->getParameterBag('base.notifier.test_recipients');
        foreach (array_merge(['tester@example.org', 'someone@lesringostars.test'], array_map(fn ($r) => str_replace(['\\', '.*', '^', '$'], ['', 'x', '', ''], $r).'', $recipients)) as $email) {
            $user = (new \ReflectionClass($userClass))->newInstanceWithoutConstructor();
            $user->setEmail($email);
            $before = false;
            if (BaseService::getService()->isDebug()) {
                foreach ($recipients as $testRecipient) {
                    if (preg_match('/'.$testRecipient.'/', $user->getEmail())) {
                        $before = true;
                        break;
                    }
                }
            }
            self::assertSame($before, $user->isTester(), $email);
        }

        $token = new Token('c4b-environment', 3600);
        self::assertSame(BaseService::getService()->getParameterBag('base.user.token_default_throttling'), (new \ReflectionProperty(Token::class, 'throttle'))->getValue($token));
        $hashIds = (new \ReflectionProperty(Token::class, 'hashIds'))->getValue($token);
        self::assertSame((new Hashids(BaseService::getService()->getSalt()))->encodeHex('c4b0'), $hashIds->encodeHex('c4b0'), 'salted with the secret');
        self::assertSame(42, (new \ReflectionProperty(Token::class, 'throttle'))->getValue(new Token('c4b-throttled', 3600, 42)));

        $setting = new SettingIntl();
        $setting->setSecure(true);
        self::assertSame(BaseService::getEnvironment(), $setting->getVault());
        self::assertTrue($setting->isSecured());
        $setting->setSecure(false);
        self::assertNull($setting->getVault());
    }
}
