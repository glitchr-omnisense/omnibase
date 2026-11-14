<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\Entities;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Omnibridge\Doctrine\Omnibase\Repository\Layout\SettingRepository;
use Base\Repository\Layout\Settings;
use Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository;
use Base\Repository\Thread\Tags;
use Omnibridge\Doctrine\Omnibase\Repository\ThreadRepository;
use Base\Repository\Threads;
use Omnibridge\Doctrine\Omnibase\Repository\User\NotificationRepository;
use Base\Repository\User\Notifications;
use Omnibridge\Doctrine\Omnibase\Repository\UserRepository;
use Base\Repository\Users;
use Base\Database\Repository\Repositories;
use Base\Repository\Aliases;
use Base\Repository\Analytics;
use Base\Repository\Extension;
use Base\Repository\Hours;
use Base\Repository\Layout;
use Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\SlotRepository;
use Base\Repository\Layout\Widget\Slots;
use Omnibridge\Doctrine\Omnibase\Repository\Layout\WidgetRepository;
use Base\Repository\Layout\Widgets;
use Base\Repository\Thread as ThreadRepositories;
use Omnibridge\Doctrine\Omnibase\Repository\ThreadIntlRepository;
use Base\Repository\ThreadTranslations;
use Base\Repository\User;
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
 *   hands out, of the persistence the host chose (step F7f) (read only: nothing written, the suite runs against an
 *   application's database too).
 * - Nothing of Doctrine in a signature: no QueryBuilder, Query, Criteria nor
 *   Collection taken or given back.
 */
final class RepositoryInterfacesTest extends KernelTestCase
{
    use \Tests\Base\Database\Backend\HostPersistence;

    private const INTERFACES = [
        Entities::class => ServiceEntityRepository::class,
        Threads::class => ThreadRepository::class,
        Tags::class => TagRepository::class,
        Users::class => UserRepository::class,
        Notifications::class => NotificationRepository::class,
        Settings::class => SettingRepository::class,
        // Step R28.
        ThreadTranslations::class => ThreadIntlRepository::class,
        Extension\TrashBalls::class => \Omnibridge\Doctrine\Omnibase\Repository\Extension\TrashBallRepository::class,
        Extension\Revisions::class => \Omnibridge\Doctrine\Omnibase\Repository\Extension\RevisionRepository::class,
        Widgets::class => WidgetRepository::class,
        Slots::class => SlotRepository::class,
        Layout\ImageCrops::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\ImageCropRepository::class,
        Layout\Redirections::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\RedirectionRepository::class,
        Layout\Semantics::class => \Omnibridge\Doctrine\Omnibase\Repository\Layout\SemanticRepository::class,
        ThreadRepositories\Likes::class => \Omnibridge\Doctrine\Omnibase\Repository\Thread\LikeRepository::class,
        ThreadRepositories\Comments::class => \Omnibridge\Doctrine\Omnibase\Repository\Thread\CommentRepository::class,
        ThreadRepositories\Mentions::class => \Omnibridge\Doctrine\Omnibase\Repository\Thread\MentionRepository::class,
        User\Connections::class => \Omnibridge\Doctrine\Omnibase\Repository\User\ConnectionRepository::class,
        User\Passkeys::class => \Omnibridge\Doctrine\Omnibase\Repository\User\PasskeyRepository::class,
        User\Tokens::class => \Omnibridge\Doctrine\Omnibase\Repository\User\TokenRepository::class,
        User\PushSubscriptions::class => \Omnibridge\Doctrine\Omnibase\Repository\User\PushSubscriptionRepository::class,
        Analytics\PageViews::class => \Omnibridge\Doctrine\Omnibase\Repository\Analytics\PageViewRepository::class,
        Analytics\Visits::class => \Omnibridge\Doctrine\Omnibase\Repository\Analytics\VisitRepository::class,
        Analytics\PageVisits::class => \Omnibridge\Doctrine\Omnibase\Repository\Analytics\PageVisitRepository::class,
        Hours\ScopedWeeks::class => \Omnibridge\Doctrine\Omnibase\Repository\Hours\ScopedWeekRepository::class,
        Hours\SpecialDays::class => \Omnibridge\Doctrine\Omnibase\Repository\Hours\SpecialDayRepository::class,
        Hours\WeekDays::class => \Omnibridge\Doctrine\Omnibase\Repository\Hours\WeekDayHoursRepository::class,
        // Step E2' (plan "omnibase agnostic").
        User\Complaints::class => \Omnibridge\Doctrine\Omnibase\Repository\User\ComplaintRepository::class,
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

    /**
     * Step R28: what the core asked by name of a repository (a finder through
     * ServiceEntityParser, a query by hand) and now asks its interface is the
     * same finder with the same arguments - the same query, the same result
     * cache.
     */
    public function testTheCoreAsksItsInterfacesTheFindersItCalledByName(): void
    {
        $thread = (new \ReflectionClass(Thread::class))->newInstanceWithoutConstructor();
        $user = (new \ReflectionClass(\Base\Entity\User::class))->newInstanceWithoutConstructor();

        $cases = [
            // the repository, the call, the finder calls it makes
            [ThreadRepository::class, fn ($r) => $r->oneBySlug('japon', false), [['findOneBySlug', ['japon']]]],
            [ThreadRepository::class, fn ($r) => $r->byStateUncached(ThreadState::FUTURE), [['findByState', [ThreadState::FUTURE]]]],
            [UserRepository::class, fn ($r) => $r->oneByUsername('marco', false), [['findOneByUsername', ['marco']]]],
            [UserRepository::class, fn ($r) => $r->oneByEmail('marco@example.org'), [['findOneByEmail', ['marco@example.org']]]],
            [UserRepository::class, fn ($r) => $r->oneById(7), [['cacheOneById', [7]]]],
            [UserRepository::class, fn ($r) => $r->byIds([7, 8]), [['cacheById', [[7, 8]]]]],
            [UserRepository::class, fn ($r) => $r->byRoles('ROLE_ADMIN'), [['cacheByRoles', ['ROLE_ADMIN']]]],
            [UserRepository::class, fn ($r) => $r->activeWithin(300, 7), [['findByIdNotEqualToAndActiveAtYoungerThan', [7, 300]]]],
            [UserRepository::class, fn ($r) => $r->activeWithin(300), [['findByActiveAtYoungerThan', [300]]]],
            [WidgetRepository::class, fn ($r) => $r->oneByPath('footer'), [['cacheOneByPath', ['footer']]]],
            [WidgetRepository::class, fn ($r) => $r->oneByPath('footer', false), [['findOneByPath', ['footer']]]],
            [WidgetRepository::class, fn ($r) => $r->every(), [['cacheAll', []]]],
            [SlotRepository::class, fn ($r) => $r->oneByPath('footer.left'), [['cacheOneByPath', ['footer.left']]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\SettingRepository::class, fn ($r) => $r->under('base.settings', true, true), [['cacheByInsensitivePathStartingWithAndBagNotEmpty', ['base.settings']]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\SettingRepository::class, fn ($r) => $r->under(null, true, false), [['findByBagNotEmpty', []]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\SettingRepository::class, fn ($r) => $r->under('base.settings', false, false), [['findByInsensitivePathStartingWith', ['base.settings']]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\SettingRepository::class, fn ($r) => $r->under(null), [['cacheAll', []]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\SettingRepository::class, fn ($r) => $r->oneByInsensitivePath('base.settings.title'), [['findOneByInsensitivePath', ['base.settings.title']]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\ImageCropRepository::class, fn ($r) => $r->oneBySlugOfImage('square', 'uuid'), [['cacheOneBySlug', ['square', ['image.source' => 'uuid']]]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\ImageCropRepository::class, fn ($r) => $r->oneClosestToRatio(1.5, 'uuid'), [['cacheOneByRatio0ClosestTo', [1.5, ['image.source' => 'uuid'], [], null, [], ['ratio0' => 'e.width0/e.height0']]]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\ImageCropRepository::class, fn ($r) => $r->oneClosestToRatioAndSize(1.5, 0.5, 0.25, 'uuid'), [['findOneByRatio0ClosestToAndWidth0ClosestToAndHeight0ClosestTo', [1.5, 0.5, 0.25, ['image.source' => 'uuid'], [], null, [], ['ratio0' => 'e.width0/e.height0']]]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Layout\SemanticRepository::class, fn ($r) => $r->every(), [['cacheAll', []]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Thread\LikeRepository::class, fn ($r) => $r->oneByThreadAndUser($thread, $user), [['findOneByThreadAndUser', [$thread, $user]]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\Thread\MentionRepository::class, fn ($r) => $r->byThread($thread), [['cacheByThread', [$thread]]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\User\ConnectionRepository::class, fn ($r) => $r->oneByUniqidAndUser('abc', $user), [['findOneByUniqidAndUser', ['abc', ['user' => $user]]]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\User\TokenRepository::class, fn ($r) => $r->oneByValueAndName('v', 'verify-email'), [['findOneByValueAndName', ['v', 'verify-email']]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\User\TokenRepository::class, fn ($r) => $r->oneByValue('v'), [['findOneByValue', ['v']]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\User\TokenRepository::class, fn ($r) => $r->oneSigningInByNameAndValue('login-token', 'v'), [['findOneByNameAndValueAndIsLogginable', ['login-token', 'v', true]]]],
            [\Omnibridge\Doctrine\Omnibase\Repository\User\TokenRepository::class, fn ($r) => $r->oneSigningInByValue('v'), [['findOneByValueAndIsLogginable', ['v']]]],
        ];
        foreach ($cases as $n => [$class, $call, $finders]) {
            $repository = self::recorded($class);
            $call($repository);
            self::assertSame($finders, $repository->calls, "$class, case $n");
        }
    }

    /**
     * The repository of $class with its finder recorded (RecordedFinder), built
     * without its constructor.
     *
     * @param class-string $class
     */
    private static function recorded(string $class): object
    {
        $code = 'return new class extends \\'.$class.' { use \\'.RecordedFinder::class.'; };';

        return eval($code);
    }

    /**
     * The entities of a class named at run time: omnibase's repository as it
     * is, a plain EntityRepository behind DoctrineEntities, and the queries
     * SelectType made (cacheById / cacheOneById, else findBy / find on the
     * identifier).
     */
    public function testRepositoriesHandOutTheEntityManagersRepositories(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        $container = static::getContainer();
        $repositories = $container->get(Repositories::class);
        $plain = $repositories->of(\Base\Entity\Thread\TagIntl::class);
        if ('doctrine' === $this->hostPersistence()) {
            $entityManager = $container->get('doctrine')->getManager();
            self::assertSame($entityManager->getRepository(Thread::class), $repositories->of(Thread::class));
            self::assertInstanceOf(\Omnibridge\Doctrine\Omnibase\DoctrineEntities::class, $plain);
        } else {
            // Another persistence's repositories (omnibridge/eloquent's in a site on eloquent): one per class.
            self::assertInstanceOf(Entities::class, $repositories->of(Thread::class));
            self::assertSame($repositories->of(Thread::class), $repositories->of(Thread::class));
            self::assertInstanceOf(Entities::class, $plain);
        }
        if ($container->has('doctrine')) {
            self::assertSame($container->get('doctrine')->getManager()->getRepository(\Base\Entity\Thread\TagIntl::class)->count([]), $plain->count(), 'the rows Doctrine counts');
        }
        self::assertSame([], $repositories->byIds(Thread::class, [-1]));
        self::assertNull($repositories->oneById(Thread::class, -1));
        self::assertNull($repositories->oneById(\Base\Entity\Thread\TagIntl::class, -1));
    }

    public function testEachInterfaceIsTheRepositoryServiceUnderSymfony(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        $container = static::getContainer();
        // Every interface Base\Repository\Aliases lists (R15, R27, R28) is its repository's service; Repositories,
        // which is no repository, is Doctrine's answer over the same entity manager. On another persistence (a site on
        // eloquent, HostPersistence) each interface is that persistence's repository implementing it.
        $services = Aliases::INTERFACES;
        unset($services[Repositories::class]);
        $doctrine = 'doctrine' === $this->hostPersistence();
        if ($doctrine) {
            self::assertInstanceOf(\Omnibridge\Doctrine\Omnibase\DoctrineRepositories::class, $container->get(Repositories::class));
        }
        foreach ($services as $interface => $service) {
            $repository = $container->get($interface);
            self::assertInstanceOf($interface, $repository);
            if ($doctrine) {
                self::assertSame($container->get($service), $repository, "$interface is the service $service");
            } else {
                self::assertSame($repository, $container->get($interface), "$interface is one service");
            }
        }
        if ($doctrine) {
            self::assertSame($container->get('doctrine')->getManager()->getRepository('App\\Entity\\User'), $container->get(Users::class));
        }
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
