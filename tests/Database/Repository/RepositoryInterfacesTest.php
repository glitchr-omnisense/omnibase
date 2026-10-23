<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\Entities;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Repository\Layout\SettingRepository;
use Base\Repository\Layout\Settings;
use Base\Repository\Thread\TagRepository;
use Base\Repository\Thread\Tags;
use Base\Repository\ThreadRepository;
use Base\Repository\Threads;
use Base\Repository\User\NotificationRepository;
use Base\Repository\User\Notifications;
use Base\Repository\UserRepository;
use Base\Repository\Users;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The repositories behind interfaces (audit "lesringostars face au master
 * plan", step R15): what the code of a site and of the core type-hints says
 * nothing of the ORM, and Doctrine's repository answers it.
 *
 * - Each interface's methods are answered by the finder call their callers
 *   made by name before - the same method, the same arguments, so the same
 *   query and the same result cache: checked on the repository with its
 *   finder recorded instead of run (no database).
 * - Under Symfony each interface is the repository service the container
 *   hands out (read only: nothing written, the suite runs against an
 *   application's database too).
 * - Nothing of Doctrine in a signature: no QueryBuilder, Query, Criteria nor
 *   Collection taken or given back.
 */
final class RepositoryInterfacesTest extends KernelTestCase
{
    private const INTERFACES = [
        Entities::class => ServiceEntityRepository::class,
        Threads::class => ThreadRepository::class,
        Tags::class => TagRepository::class,
        Users::class => UserRepository::class,
        Notifications::class => NotificationRepository::class,
        Settings::class => SettingRepository::class,
    ];

    public function testEachRepositoryImplementsItsInterface(): void
    {
        foreach (self::INTERFACES as $interface => $repository) {
            self::assertTrue(interface_exists($interface), $interface);
            self::assertContains($interface, class_implements($repository), "$repository implements $interface");
            self::assertTrue(is_subclass_of($interface, Entities::class) || Entities::class === $interface, "$interface extends Entities");
        }
    }

    public function testNoSignatureSpeaksDoctrine(): void
    {
        foreach (array_keys(self::INTERFACES) as $interface) {
            foreach ((new \ReflectionClass($interface))->getMethods() as $method) {
                $types = [(string) $method->getReturnType()];
                foreach ($method->getParameters() as $parameter) {
                    $types[] = (string) $parameter->getType();
                }
                foreach ($types as $type) {
                    self::assertDoesNotMatchRegularExpression('/Doctrine\\\\|QueryBuilder|\\bQuery\\b|Criteria|Collection/', $type, "$interface::{$method->getName()}() names $type");
                }
            }
        }
    }

    public function testThreadsAreAnsweredByTheFinderCallsTheyReplace(): void
    {
        $parent = (new \ReflectionClass(Thread::class))->newInstanceWithoutConstructor();
        $states = [ThreadState::PUBLISH, ThreadState::ARCHIVE];
        $order = ['publishedAt' => 'DESC'];
        $at = ['publishedAt' => new \DateTimeImmutable('2026-10-21')];

        $calls = [
            [fn (Threads $r) => $r->oneBySlug('japon'), 'cacheOneBySlug', ['japon']],
            [fn (Threads $r) => $r->oneBySlugAndState('japon', $states), 'cacheOneBySlugWithRouteAndState', [['slug' => 'japon'], 'slug', $states, [], []]],
            [fn (Threads $r) => $r->oneBySlugAndState(null, $states, $order), 'cacheOneBySlugWithRouteAndState', [['slug' => null], 'slug', $states, [], $order]],
            [fn (Threads $r) => $r->oneByState(ThreadState::PUBLISH, $order), 'cacheOneByState', [ThreadState::PUBLISH, [], $order]],
            [fn (Threads $r) => $r->first(['startedAt' => 'DESC']), 'cacheOneBy', [[], ['startedAt' => 'DESC']]],
            [fn (Threads $r) => $r->byState($states, $order), 'cacheByState', [$states, [], $order]],
            [fn (Threads $r) => $r->oneByParentAndSlugAndState($parent, 'la-jument-de-feu', $states, $order), 'cacheOneByParentAndSlugAndState', [$parent, 'la-jument-de-feu', $states, [], $order]],
            [fn (Threads $r) => $r->byParentAndState($parent, $states, $order, 21, 40), 'cacheByParentAndState', [$parent, $states, [], $order, 21, 40]],
            [fn (Threads $r) => $r->byParentAndState(null, $states), 'cacheByParentAndState', [null, $states, [], [], null, null]],
            [fn (Threads $r) => $r->byParentAndStatePublishedWithin($parent, $states, '+8 days', $order), 'cacheByParentAndStateAndPublishedAtWithin', [$parent, $states, '+8 days', [], $order]],
            [fn (Threads $r) => $r->oneByStateAndSlugStartingWith($states, 'preamble'), 'cacheOneByStateAndSlugStartingWith', [$states, 'preamble']],
            [fn (Threads $r) => $r->countByStatePublishedYoungerThan(ThreadState::PUBLISH, 172800), 'countByStateAndPublishedAtYoungerThan', [ThreadState::PUBLISH, 172800]],
            [fn (Threads $r) => $r->previousOne($at, $states), 'cachePreviousOneAndState', [$at, $states]],
            [fn (Threads $r) => $r->previousOne($at, $states, Thread::class), 'cachePreviousOneByClassOfAndState', [$at, Thread::class, $states]],
            [fn (Threads $r) => $r->nextOne($at, $states), 'cacheNextOneAndState', [$at, $states]],
            [fn (Threads $r) => $r->nextOne($at, $states, Thread::class), 'cacheNextOneByClassOfAndState', [$at, Thread::class, $states]],
        ];

        foreach ($calls as [$call, $finder, $arguments]) {
            $repository = new class extends ThreadRepository {
                use RecordedFinder;
            };
            $call($repository);
            self::assertSame([[$finder, $arguments]], $repository->calls, $finder);
        }
    }

    public function testTagsAndUsersAreAnsweredByTheFinderCallsTheyReplace(): void
    {
        $tags = new class extends TagRepository {
            use RecordedFinder;
        };
        $tags->oneBySlug('vivaldi');
        $tags->bySlugs(['vivaldi', 'japon']);
        self::assertSame([['cacheOneBySlug', ['vivaldi']], ['cacheBySlug', [['vivaldi', 'japon']]]], $tags->calls);

        $users = new class extends UserRepository {
            use RecordedFinder;
        };
        $users->oneByUsername('marco');
        self::assertSame([['cacheOneByUsername', ['marco']]], $users->calls);
    }

    public function testEachInterfaceIsTheRepositoryServiceUnderSymfony(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        $container = static::getContainer();
        $services = [
            Users::class => 'App\\Repository\\UserRepository',
            Threads::class => ThreadRepository::class,
            Tags::class => TagRepository::class,
            Notifications::class => NotificationRepository::class,
            Settings::class => SettingRepository::class,
        ];
        foreach ($services as $interface => $service) {
            $repository = $container->get($interface);
            self::assertInstanceOf($interface, $repository);
            self::assertSame($container->get($service), $repository, "$interface is the service $service");
        }
        self::assertSame($container->get('doctrine')->getManager()->getRepository('App\\Entity\\User'), $container->get(Users::class));
    }
}

/**
 * A repository with its finder recorded instead of run: what each method asks
 * it, by name and arguments; built without its constructor's entity manager.
 * A one-entity finder answers null, a count 0, a list a query whose result is
 * empty.
 */
trait RecordedFinder
{
    /** @var list<array{string, array<mixed>}> */
    public array $calls = [];

    public function __construct()
    {
    }

    /**
     * @param string       $method
     * @param array<mixed> $arguments
     */
    public function __call($method, $arguments): mixed
    {
        $this->calls[] = [$method, $arguments];
        if (str_starts_with($method, 'count')) {
            return 0;
        }
        if (preg_match('/^(cache|find)(One|PreviousOne|NextOne)/', $method)) {
            return null;
        }

        return new class {
            /** @return list<object> */
            public function getResult(): array
            {
                return [];
            }
        };
    }
}
