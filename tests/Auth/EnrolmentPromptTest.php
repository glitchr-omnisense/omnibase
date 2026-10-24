<?php

namespace Tests\Base\Auth;

use Base\Auth\EnrolmentPrompt;
use Base\Service\SecurityPolicy;
use Base\Service\SettingBagInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * When a signed-in visitor is sent to the second factor's enrolment, the rule
 * both hosts ask (step R29: Symfony's SecurityEnrolmentSubscriber,
 * omnibridge/laravel's enrolment middleware).
 */
class EnrolmentPromptTest extends TestCase
{
    /** @param list<string> $requiredRoles */
    private function prompt(array $requiredRoles = ['ROLE_STAFF'], bool $postpone = true): EnrolmentPrompt
    {
        $bag = $this->createStub(SettingBagInterface::class);
        $bag->method('getScalar')->willReturn(null);

        return new EnrolmentPrompt(new SecurityPolicy($bag, new RoleHierarchy(['ROLE_STAFF' => ['ROLE_USER']]), $requiredRoles, $postpone));
    }

    public function testOnlyAPlainPageIsInterrupted(): void
    {
        $this->assertTrue(EnrolmentPrompt::interrupts('GET', false, ['text/html', '*/*'], '/japon/blog'));
        $this->assertTrue(EnrolmentPrompt::interrupts('GET', false, [], '/'));
        $this->assertFalse(EnrolmentPrompt::interrupts('POST', false, [], '/japon/blog'), 'a form posted lands where it was going');
        $this->assertFalse(EnrolmentPrompt::interrupts('GET', true, [], '/japon/blog'), 'a script gets its answer');
        $this->assertFalse(EnrolmentPrompt::interrupts('GET', false, ['application/json'], '/notifications/latest'));
        foreach (['/settings/security-required', '/login', '/logout', '/register', '/reset-password', '/connect/google', '/_wdt/1'] as $path) {
            $this->assertFalse(EnrolmentPrompt::interrupts('GET', false, [], $path), $path);
        }
    }

    public function testASecondFactorRequiredOfTheAccountIsDueUntilSkippedWhereALaterIsAllowed(): void
    {
        $staff = new InMemoryUser('staff', null, ['ROLE_STAFF']);
        $member = new InMemoryUser('member', null, ['ROLE_USER']);

        $prompt = $this->prompt();
        $this->assertTrue($prompt->due($staff, false, false, false));
        $this->assertFalse($prompt->due($staff, false, true, false), '"not now" lasts the session');
        $this->assertFalse($prompt->due($staff, false, false, true), 'not asked of someone impersonating the account');
        $this->assertFalse($prompt->due($member, false, false, false));
        $this->assertFalse($prompt->due(null, false, false, false));
        $this->assertFalse($prompt->page($staff, false), 'the page: the mandatory enrolment');
        $this->assertNull($prompt->page($member, false), 'nothing to ask');

        $this->assertTrue($this->prompt(['ROLE_STAFF'], false)->due($staff, false, true, false), 'no "later" where the application allows none');
    }

    public function testTheOfferAfterANewDeviceIsMadeWhileTheAccountHasNoSecondFactor(): void
    {
        $member = new InMemoryUser('member', null, ['ROLE_USER']);
        $enrolled = new class('enrolled', null, ['ROLE_USER']) {
            /** @param list<string> $roles */
            public function __construct(public string $name, public ?string $password, public array $roles)
            {
            }

            /** @return list<string> */
            public function getRoles(): array
            {
                return $this->roles;
            }

            public function isEmailAuthEnabled(): bool
            {
                return true;
            }
        };

        $prompt = $this->prompt();
        $this->assertTrue($prompt->due($member, true, false, false));
        $this->assertFalse($prompt->forgetsTheOffer($member, true));
        $this->assertTrue($prompt->page($member, true), 'the page: the optional offer');

        $this->assertFalse($prompt->due($enrolled, true, false, false));
        $this->assertTrue($prompt->forgetsTheOffer($enrolled, true), 'moot once a second factor exists');
        $this->assertTrue($prompt->forgetsTheOffer(null, true));
        $this->assertNull($prompt->page($enrolled, true));
    }
}
