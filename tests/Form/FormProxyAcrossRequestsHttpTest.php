<?php

namespace Tests\Base\Form;

use Base\Form\FormProxy;
use Base\Form\Type\SecurityLoginType;
use Base\Form\Type\SecurityRegistrationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\Http\HttpTestTrait;

/**
 * One kernel, several requests - a test client that does not reboot, a
 * worker: the sign-in page keeps its form under "form:login", and the sign-up
 * page, which asks the form proxy for a processor of the same name, got the
 * sign-in form back and failed on it (a 500: "Neither the property email ...
 * in FormView"). Found by genealogist's suite.
 */
class FormProxyAcrossRequestsHttpTest extends KernelTestCase
{
    use HttpTestTrait;

    public function testTheSignUpPageAfterTheSignInPageInTheSameKernel(): void
    {
        $this->bootHost();
        // what the sign-in page leaves in the form proxy of the kernel that served it
        static::getContainer()->get(FormProxy::class)->createProcessor('form:login', SecurityLoginType::class);

        $register = static::$kernel->handle(Request::create('/register'));
        self::assertSame(200, $register->getStatusCode(), substr(strip_tags((string) $register->getContent()), 0, 400));
        // The sign-up form, not the sign-in one: its password asked twice, and an address - under the
        // form's name, the core's (_base_security_registration) or the one of an application's own type.
        self::assertSame(1, preg_match('/name="([^"\[]+)\[plainPassword\]\[first\]"/', (string) $register->getContent(), $form), 'the sign-up form, not the sign-in one');
        self::assertStringContainsString('name="'.$form[1].'[email]"', (string) $register->getContent());
    }

    public function testAProcessorOfTheSameNameForAnotherTypeIsAnotherOne(): void
    {
        $this->bootHost();
        $proxy = static::getContainer()->get(FormProxy::class);
        $proxy->reset();

        $login = $proxy->createProcessor('form:login', SecurityLoginType::class);
        self::assertSame($login, $proxy->createProcessor('form:login', SecurityLoginType::class), 'the same type: the same processor');
        $register = $proxy->createProcessor('form:login', SecurityRegistrationType::class);
        self::assertNotSame($login, $register);
        self::assertInstanceOf(SecurityRegistrationType::class, $register->getForm()->getConfig()->getType()->getInnerType());

        $proxy->reset();
        self::assertTrue($proxy->empty(), 'nothing kept for the next request');
    }
}
