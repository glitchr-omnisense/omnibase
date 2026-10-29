<?php

namespace Tests\Base\Subscriber;

use Base\Entity\User\Token;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Base\EntityDispatcher\Event\UserEvent;
use Base\EntitySubscriber\UserSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Base\Http\HttpTestTrait;

/**
 * The user.* events reach the core's UserSubscriber: an account registered is asked to verify
 * its address, an approval confirmed, a closing said goodbye to. No configuration registered the
 * subscriber since the back office left the core (80088b2d), so none of these was ever sent;
 * and its calls named notifier methods that do not exist (sendUserWelcomeBack($user) without
 * its token) and read the token storage's token without checking there was one.
 */
final class UserSubscriberTest extends KernelTestCase
{
    use HttpTestTrait;

    protected function setUp(): void
    {
        $this->bootHost();

        $request = Request::create('/');
        $request->setSession(static::getContainer()->get('session.factory')->createSession());
        static::getContainer()->get('request_stack')->push($request);
    }

    protected function tearDown(): void
    {
        static::getContainer()->get('security.token_storage')->setToken(null);
        $this->removeUsers();
        parent::tearDown();
    }

    public function testTheUserEventsHaveTheCoresListener(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        foreach ([UserEvent::REGISTER, UserEvent::APPROVAL, UserEvent::ENABLED, UserEvent::DISABLED] as $event) {
            $listeners = array_filter(
                $dispatcher->getListeners($event),
                fn ($listener) => \is_array($listener) && $listener[0] instanceof UserSubscriber
            );
            $this->assertNotEmpty($listeners, "$event has UserSubscriber's listener");
        }
    }

    public function testARegistrationAsksToVerifyTheAddress(): void
    {
        $user = $this->createUser();

        $token = $user->getToken('verify-email');
        $this->assertNotNull($token, 'a verification token is made');
        $this->assertTrue($token->isValid());
        $this->assertContains(
            static::getContainer()->get('translator')->trans('@notifications.verifyEmail.check'),
            $this->flashes(),
            'and the verification notice sent'
        );
    }

    public function testAVerifiedRegistrationIsNotAskedAgain(): void
    {
        $em = $this->entityManager();
        $class = 'App\\Entity\\User';
        $user = new $class();
        $name = 'test'.bin2hex(random_bytes(4));
        if (method_exists($user, 'setUsername')) {
            $user->setUsername($name);
        }
        $user->setEmail($name.'@example.org');
        $user->setPlainPassword('test-'.$name);
        $user->verify();
        $em->persist($user);
        $em->flush();
        $em->remove($user);
        $em->flush();

        $this->assertNull($user->getToken('verify-email'));
    }

    public function testAnApprovalIsConfirmedAndItsRequestRevoked(): void
    {
        $user = $this->createUser();
        $user->approve(false);
        $request = new Token('admin-approval');
        $user->addToken($request);
        $this->entityManager()->flush();

        $user->approve();
        $this->entityManager()->flush();
        $this->settle();

        $this->assertTrue($request->isRevoked(), 'the approval request is revoked');
        $this->assertContains(
            static::getContainer()->get('translator')->trans('@notifications.adminApproval.approval'),
            $this->flashes(),
            'and the confirmation sent'
        );
    }

    public function testClosingOnesAccountSaysGoodbye(): void
    {
        $user = $this->createUser();
        static::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $user->disable();
        $this->entityManager()->flush();

        $this->assertContains(
            static::getContainer()->get('translator')->trans('@notifications.accountGoodbye.success'),
            $this->flashes()
        );
    }

    public function testAnAccountChangedWithNobodySignedInDoesNotFail(): void
    {
        $user = $this->createUser();

        $user->disable();
        $this->entityManager()->flush();
        $user->enable();
        $this->entityManager()->flush();

        $this->assertFalse($user->isDisabled());
    }

    /** What the response does once it goes out: the listeners' changes flushed (EntityDispatcherFlushSubscriber). */
    private function settle(): void
    {
        foreach (AbstractEventDispatcher::live() as $dispatcher) {
            $dispatcher->flushPending();
        }
    }

    /** @return list<string> every message waiting in the session, whatever its type */
    private function flashes(): array
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()->getSession();

        return array_merge(...array_values($session->getFlashBag()->peekAll()) ?: [[]]);
    }
}
