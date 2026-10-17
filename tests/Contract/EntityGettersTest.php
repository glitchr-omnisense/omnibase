<?php

namespace Tests\Base\Contract;

use Base\Contract\Link;
use Base\Contract\Runtime;
use Base\Entity\Layout\Semantic;
use Base\Entity\Layout\ShortLink;
use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Enum\WorkflowState;
use Base\Exception\MissingContractException;
use Base\Service\BaseService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The entities' getters C3e moved off BaseTrait's services onto the contracts
 * (plan "omnibase agnostique"), under Symfony: each answers what the service
 * it used answers - the router, the authorization checker, the Localizer -
 * compared here with the old path, one by one, as ContractAdaptersTest
 * compares the adapters. tests/BareTest's
 * testTheEntitiesAnswerThroughTheContractsWithoutTheFramework runs the same
 * getters where there is no framework.
 */
final class EntityGettersTest extends KernelTestCase
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

    public function testLinksReferenceTypesAreTheRoutersConstants(): void
    {
        self::assertSame(UrlGeneratorInterface::ABSOLUTE_URL, Link::ABSOLUTE_URL);
        self::assertSame(UrlGeneratorInterface::ABSOLUTE_PATH, Link::ABSOLUTE_PATH);
        self::assertSame(UrlGeneratorInterface::RELATIVE_PATH, Link::RELATIVE_PATH);
        self::assertSame(UrlGeneratorInterface::NETWORK_PATH, Link::NETWORK_PATH);
    }

    /**
     * Semantic::__toLink() and getUrl(), ShortLink::__toLink() and Link::to() give the router's
     * strings for the four reference types (BaseTrait's getRouter() is the same advanced_router).
     */
    public function testLinksAreTheRoutersAddresses(): void
    {
        $router = static::getContainer()->get('advanced_router');
        $types = [Link::ABSOLUTE_URL, Link::ABSOLUTE_PATH, Link::RELATIVE_PATH, Link::NETWORK_PATH];
        $checked = 0;

        foreach ([['security_login', []], ['user_profile', []], ['short_redirect', ['slug' => 'x7Kq2']]] as [$route, $parameters]) {
            if (null === $router->getRouteCollection()->get($route)) {
                continue;
            }

            $semantic = (new \ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
            $semantic->setRouteName($route);
            $semantic->setRouteParameters($parameters);
            foreach ($types as $type) {
                self::assertSame($router->generate($route, $parameters, $type), $semantic->__toLink($type), $route.', type '.$type);
                self::assertSame($router->generate($route, $parameters, $type), Link::to($route, $parameters, $type), $route.', Link::to(), type '.$type);
                self::assertSame($semantic->getRouter()->generate($route, $parameters, $type), $semantic->__toLink($type), $route.', BaseTrait\'s router');
            }
            self::assertSame($router->generate($route, $parameters), $semantic->getUrl(), $route);
            self::assertSame($router->generate($route, $parameters), $semantic->__toLink(), $route);
            ++$checked;
        }
        self::assertGreaterThan(0, $checked, 'a route of the core was compared');

        // A route the router cannot make: null, as before.
        $semantic->setRouteName('no_such_route_c3e');
        self::assertNull($semantic->__toLink());

        if (null !== $router->getRouteCollection()->get('short_redirect')) {
            $short = (new \ReflectionClass(ShortLink::class))->newInstanceWithoutConstructor();
            self::assertNull($short->__toLink());
            $short->setSlug('x7Kq2');
            foreach ($types as $type) {
                self::assertSame($router->generate('short_redirect', ['slug' => 'x7Kq2'], $type), $short->__toLink([], $type), 'short link, type '.$type);
                self::assertSame($router->generate('short_redirect', ['utm' => 'mail', 'slug' => 'x7Kq2'], $type), $short->__toLink(['utm' => 'mail'], $type), 'short link with a parameter, type '.$type);
            }
            self::assertSame($router->generate('short_redirect', ['slug' => 'x7Kq2']), $short->__toLink());
        }
    }

    /** A registry without Urls is said aloud by Semantic, never taken for a missing route. */
    public function testALinkWithoutTheUrlsContractIsSaidAloud(): void
    {
        $semantic = (new \ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
        $semantic->setRouteName('security_login');
        $semantic->setRouteParameters([]);

        $container = Runtime::getContainer();
        Runtime::setContainer(null);
        try {
            $this->expectException(MissingContractException::class);
            $semantic->__toLink();
        } finally {
            Runtime::setContainer($container);
        }
    }

    /**
     * Thread::isVisible() and User::isGranted() ask the Access contract what they asked BaseService:
     * the same authorization checker, nobody signed in then an administrator, a published, a draft
     * and a secret thread. The old isVisible() also asked whether the owners held the token
     * storage's token, never true: its answers were BaseService::isGranted('ROLE_ADMIN') or
     * approved, published and not secret, compared here.
     */
    public function testVisibilityAndGrantsAreTheAuthorizationCheckers(): void
    {
        $container = static::getContainer();
        $checker = $container->get('security.authorization_checker');
        $userClass = 'App\\Entity\\User';
        $member = (new \ReflectionClass($userClass))->newInstanceWithoutConstructor();

        $threads = [];
        foreach ([ThreadState::PUBLISH, ThreadState::DRAFT, ThreadState::SECRET] as $state) {
            $thread = new Thread();
            $thread->setState($state);
            $thread->setWorkflow(WorkflowState::APPROVED);
            $threads[$state] = $thread;
        }

        $seen = [];
        foreach ([null, new InMemoryUser('c3e-admin', null, ['ROLE_ADMIN'])] as $user) {
            $container->get('security.token_storage')->setToken($user ? new UsernamePasswordToken($user, 'main', $user->getRoles()) : null);
            $label = $user ? 'an administrator' : 'nobody';

            foreach ($threads as $state => $thread) {
                $before = BaseService::getService()->isGranted('ROLE_ADMIN') || ($thread->isApproved() && $thread->isPublished() && !$thread->isSecret());
                self::assertSame($before, $thread->isVisible(), $state.', '.$label);
                $seen[$label][$state] = $thread->isVisible();
            }

            foreach (['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPERADMIN', 'IS_AUTHENTICATED'] as $role) {
                self::assertSame($checker->isGranted($role, $member), $member->isGranted($role), $role.', '.$label);
                self::assertSame(BaseService::getService()->isGranted($role, $member), $member->isGranted($role), $role.', '.$label.', BaseService');
            }
        }

        self::assertSame([ThreadState::PUBLISH => true, ThreadState::DRAFT => false, ThreadState::SECRET => false], $seen['nobody'], 'nobody sees the published thread only');
        self::assertSame([true, true, true], array_values($seen['an administrator']), 'an administrator sees them all');
        self::assertTrue($member->isGranted('ROLE_ADMIN'), 'the administrator is granted ROLE_ADMIN on a member');
    }
}
