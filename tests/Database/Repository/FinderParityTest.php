<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\ServiceEntityParser;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Database\Repository\Finder\Key;
use Base\Entity\Layout\ImageCrop;
use Base\Entity\Thread;
use Base\Entity\Thread\Comment;
use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Enum\CommentState;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The finder language, before and after its parser left Doctrine (plan "omnibase agnostic",
 * 2026-11-01, step F1): the same method names give the same parse and the same query.
 *
 * For each finder call of the scenarios (the shapes the core and lesringostars call, and one of
 * each word of the language), two things are compared with finder-parity.json, recorded on the
 * code of before F1 (FINDER_PARITY_RECORD=1):
 *
 * - **the parse**: the internal method the name leads to, its arguments, the criteria and their
 *   options, the separator, the column, cacheable and eagerly - read from the parser of before by
 *   reflection, from Base\Database\Repository\Finder\Parser's Query after;
 * - **the query**, where the finder hands one back (find...By, findAll, Last, AtMost, Previous,
 *   Next, Randomly): its DQL, its parameters, its first and maximum results; where it runs it
 *   (findOne, count, cacheOne...), what it gave on the harness's database, or the exception.
 *
 * lesringostars' own FinderParityTest compares results on the site's data.
 */
final class FinderParityTest extends KernelTestCase
{
    private const FIXTURE = __DIR__.'/finder-parity.json';

    /** The fixed moment of the scenarios that compare with one. */
    private const AT = '2026-05-01T12:00:00+00:00';

    /** @return list<array{string, string, list<mixed>}> entity, method, arguments */
    private static function scenarios(): array
    {
        $at = new \DateTimeImmutable(self::AT);

        return [
            // Equality, the separators, the plain calls.
            [Thread::class, 'findBySlug', ['a-slug']],
            [Thread::class, 'findOneBySlug', ['a-slug']],
            [Thread::class, 'cacheOneBySlug', ['a-slug']],
            [Thread::class, 'findByState', ['publish']],
            [Thread::class, 'findByStateAndSlug', ['publish', 'a-slug']],
            [Thread::class, 'findBySlugOrState', ['a-slug', 'publish']],
            [Thread::class, 'findBy', [['state' => 'publish'], ['id' => 'DESC'], 5, 10]],
            [Thread::class, 'findAll', []],
            [Thread::class, 'cacheAll', [['id' => 'DESC']]],
            [Thread::class, 'findByStateAndTags', [['publish', 'draft'], [1, 2]]],
            [Thread::class, 'cacheBy', [['state' => 'publish']]],
            // Comparisons, dates, strings.
            [Thread::class, 'findBySlugNotEqualTo', ['a-slug']],
            [Thread::class, 'findByIdGreaterThan', [5]],
            [Thread::class, 'findByIdLowerEqualTo', [5]],
            [Thread::class, 'findByPublishedAtOlderThan', ['-1 year']],
            [Thread::class, 'findByPublishedAtYoungerEqualTo', ['-1 day']],
            [Thread::class, 'findByPublishedAtIsOver', []],
            [Thread::class, 'findByPublishedAtIsNotOver', []],
            [Thread::class, 'findByPublishedAtGreaterThan', [$at]],
            [Thread::class, 'findByParentNull', []],
            [Thread::class, 'findByParentNotNull', []],
            [Thread::class, 'findBySlugEmpty', []],
            [Thread::class, 'findBySlugStartingWith', ['a-']],
            [Thread::class, 'findBySlugEndingWith', ['-slug']],
            [Thread::class, 'findBySlugNotStartingWith', ['a-']],
            [Thread::class, 'findByInsensitiveSlug', ['A-Slug']],
            [Thread::class, 'findByPartialSlug', ['slug']],
            [Thread::class, 'findByStateAndBut', ['publish', 5]],
            [Thread::class, 'findByStateAndInstanceOf', ['publish', Thread::class]],
            [Thread::class, 'findByStateAndClassOf', ['publish', Thread::class]],
            [Thread::class, 'cacheByInstanceOf', [[Thread::class], [], [], null, null, ['id']]],
            // The specials.
            [Thread::class, 'findAtMost3ByState', ['publish']],
            [Thread::class, 'findLast2ByState', ['publish']],
            [Thread::class, 'findLastOneByState', ['publish']],
            [Thread::class, 'findPreviousByState', [['id' => 5], 'publish']],
            [Thread::class, 'findNext2ByState', [['id' => 5], 'publish']],
            [Thread::class, 'cachePreviousOneByClassOfAndState', [['id' => 5], Thread::class, 'publish']],
            [Thread::class, 'cacheNextOneByClassOfAndState', [['publishedAt' => $at], Thread::class, 'publish']],
            [Thread::class, 'findRandomlyByState', ['publish']],
            // The core's own calls (ThreadRepository, UserRepository, ImageCropRepository...).
            [Thread::class, 'cacheOneBySlugWithRouteAndState', [['slug' => 'a-slug'], 'slug', ['publish'], [], ['publishedAt' => 'DESC']]],
            [Thread::class, 'cacheByParentAndStateAndPublishedAtWithin', [null, ['publish'], '1 week', [], ['publishedAt' => 'DESC']]],
            [Thread::class, 'cacheOneByStateAndSlugStartingWith', [['publish'], 'a-']],
            [Thread::class, 'countByState', ['publish']],
            [Thread::class, 'countByStateAndPublishedAtYoungerThan', ['publish', '-1 month']],
            [Thread::class, 'distinctCountByState', ['publish']],
            [Thread::class, 'countForParentByState', ['publish']],
            [Thread::class, 'lengthOfTagsByState', ['publish']],
            [User::class, 'findByIdNotEqualToAndActiveAtYoungerThan', [5, 300]],
            [User::class, 'findByActiveAtYoungerThan', [300]],
            [User::class, 'cacheByRoles', [['ROLE_ADMIN']]],
            [User::class, 'findOneByEmail', ['someone@example.org']],
            [User::class, 'cacheOneByEmail', ['someone@example.org']],
            [User::class, 'findByIdOrEmail', [5, 'someone@example.org']],
            [Notification::class, 'findByIsReadAndSentAtOlderThan', [true, '-1 month']],
            [Notification::class, 'findByUserAndIsRead', [5, false]],
            [ImageCrop::class, 'cacheOneByRatio0ClosestTo', [1.5, ['image.source' => 'a.jpg'], [], [], ['ratio0' => 'e.width0/e.height0']]],
            [ImageCrop::class, 'findOneByRatio0ClosestToAndWidth0ClosestToAndHeight0ClosestTo', [1.5, 300, 200, ['image.source' => 'a.jpg'], [], [], ['ratio0' => 'e.width0/e.height0']]],
        ];
    }

    public function testTheSameNamesGiveTheSameParseAndQuery(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $observed = [];
        foreach (self::scenarios() as [$entity, $method, $arguments]) {
            $repository = $entityManager->getRepository($entity);
            self::assertInstanceOf(ServiceEntityRepository::class, $repository, $entity);
            $key = $entity.'::'.$method.'#'.md5(serialize(self::normalise($arguments)));
            $observed[$key] = ['parse' => self::parse($repository, $method, $arguments), 'query' => self::call($repository, $method, $arguments)];
        }

        if (getenv('FINDER_PARITY_RECORD')) {
            file_put_contents(self::FIXTURE, json_encode($observed, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n");
            self::markTestSkipped('Recorded '.\count($observed).' finder calls in '.basename(self::FIXTURE).'.');
        }

        $recorded = json_decode((string) file_get_contents(self::FIXTURE), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(array_keys($recorded), array_keys($observed), 'the same scenarios');
        // The query depends on the mapping (ClassOf lists the entity's subclasses) and a running finder's
        // result on the rows: both are compared on the harness, where the fixture was recorded. In an
        // application's suite (this package in its vendor/: its own entities and data) the parse alone
        // is compared, and the application compares its own results (lesringostars' FinderParityTest).
        $harness = false !== getenv('HARNESS_SUITE_BOOTSTRAP');
        foreach ($recorded as $key => $before) {
            if (!$harness) {
                $before['query'] = $observed[$key]['query'] = 'not compared: the application\'s mapping and rows';
            }
            $before['query'] = self::subclassesSorted($before['query']);
            $observed[$key]['query'] = self::subclassesSorted($observed[$key]['query']);
            self::assertSame($before['parse'], $observed[$key]['parse'], $key.': the same parse');
            self::assertSame($before['query'], $observed[$key]['query'], $key.': the same query or result');
        }
    }

    /**
     * The repositories' hand-written queries rewritten on the finder language (step E2): each method
     * gives what its query of before gave (HandWrittenQueries, word for word), the same rows in the
     * same order, on rows written here for the purpose - redirections (exact and by prefix, the
     * case of letters), text overrides, special days with and without a scope, a scoped week, page
     * views and visits, comments under a live thread, under one in the trash and in the visitors' book
     * (step E2b) - and on the harness's empty tables for the others (the query runs on
     * SQLite: its groups, aggregates and joins compile). lesringostars' FinderParityTest runs the
     * same on the site's data. The rows are written in a transaction, rolled back after.
     */
    public function testTheRewrittenQueriesGiveTheRowsOfBefore(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $entityManager->persist(new \Base\Entity\Layout\Redirection('/old-page', '/new-page'));
            $entityManager->persist((new \Base\Entity\Layout\Redirection('/Mixed/Case', '/target'))->setEnabled(true));
            $entityManager->persist(new \Base\Entity\Layout\Redirection('/legacy/*', '/archive/*'));
            $entityManager->persist((new \Base\Entity\Layout\Redirection('/off', '/on'))->setEnabled(false));
            foreach ([['messages', 'fr', 'a.key', 'Un'], ['messages', 'en', 'a.key', 'One'], ['forms', 'fr', 'b_key', 'Deux']] as [$domain, $locale, $key, $value]) {
                $entityManager->persist((new \Base\Entity\Layout\TextOverride())->setDomain($domain)->setLocale($locale)->setKey($key)->setValue($value));
            }
            $entityManager->persist(new \Base\Entity\Hours\SpecialDay($now->modify('+3 days'), $now->modify('+5 days'), 'Holidays'));
            $entityManager->persist(new \Base\Entity\Hours\SpecialDay($now->modify('+1 day'), null, 'Inventory', 'office'));
            $entityManager->persist(new \Base\Entity\Hours\SpecialDay($now->modify('-90 days'), null, 'Long ago'));
            $entityManager->persist(new \Base\Entity\Hours\ScopedWeek('office', [1 => [['09:00', '17:00']]]));
            foreach ([[Thread::class, 7, '-2 days'], [Thread::class, 8, null], [User::class, 7, '+2 days'], [Thread::class, 7, '-1 day']] as [$class, $id, $permanent]) {
                $trashBall = (new \Base\Entity\Extension\TrashBall())->setEntityClass($class);
                $trashBall->setEntityId($id);
                $trashBall->setAction(\Base\Enum\EntityAction::DELETE);
                $trashBall->setCreatedAt(new \DateTime());
                null === $permanent || $trashBall->setPermanentAfter(new \DateTime($permanent));
                $entityManager->persist($trashBall);
            }
            $entityManager->flush();

            // Step E2b: comments under a live thread, under a thread in the trash, in the visitors' book (one
            // with a reply), and one waiting; their dates and states set after the flush (the creation stamp
            // and the spam check have their say at the persist).
            $live = new Thread(null, null, 'A thread commented', 'a-thread-commented-'.bin2hex(random_bytes(3)));
            $trashed = new Thread(null, null, 'A thread in the trash', 'a-thread-in-the-trash-'.bin2hex(random_bytes(3)));
            $trashed->setDeletedAt(new \DateTime('-1 hour'));
            $book = (new Comment())->setName('Anne')->setContent('In the book');
            $written = [
                [$book, CommentState::APPROVED],
                [(new Comment())->setName('Bob')->setContent('A reply in the book')->setParent($book), CommentState::APPROVED],
                [(new Comment($live))->setName('Carl')->setContent('Waiting'), CommentState::PENDING],
                [(new Comment($live))->setName('Dora')->setContent('On the live thread'), CommentState::APPROVED],
                [(new Comment($trashed))->setName('Eve')->setContent('Under the trashed thread'), CommentState::APPROVED],
            ];
            $entityManager->persist($live);
            $entityManager->persist($trashed);
            foreach ($written as [$comment]) {
                $entityManager->persist($comment);
            }
            $entityManager->flush();
            foreach ($written as $i => [$comment, $state]) {
                $entityManager->createQuery('UPDATE '.Comment::class.' c SET c.createdAt = :at, c.state = :state WHERE c.id = :id')
                    ->setParameter('at', $now->modify('-'.(10 - $i).' minutes'))->setParameter('state', $state)->setParameter('id', $comment->getId())
                    ->execute();
            }
            [$bookId, $onLiveId, $onTrashedId] = [$book->getId(), $written[3][0]->getId(), $written[4][0]->getId()];
            [$liveId, $trashedId] = [$live->getId(), $trashed->getId()];

            $views = $entityManager->getRepository(\Base\Entity\Analytics\PageView::class);
            $visits = $entityManager->getRepository(\Base\Entity\Analytics\Visit::class);
            foreach ([['/', 0], ['/', 0], ['/', 40 * 86400], ['/about', 3600]] as [$path, $ago]) {
                $views->incrementView($path, $now->modify('-'.$ago.' seconds'));
                $visits->recordPresence(\Base\Entity\Analytics\Visit::TYPE_VISITOR, md5($path.$ago), $now->modify('-'.$ago.' seconds'));
            }
            $views->incrementView('/', $now, \Base\Entity\Analytics\PageView::SOURCE_BOT);
            $visits->recordPresence(\Base\Entity\Analytics\Visit::TYPE_USER, '1', $now);
            $entityManager->clear();

            $repository = static fn (string $class) => $entityManager->getRepository($class);
            $latest = static function (string $class) use ($entityManager): ?object {
                $id = $entityManager->getClassMetadata($class)->getSingleIdentifierFieldName();

                return $entityManager->createQueryBuilder()->select('x')->from($class, 'x')->orderBy('x.'.$id, 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
            };

            // The trashed thread read with the trash in view: Thread being the root of an inheritance, Doctrine
            // loads a reference to it (no proxy of an unknown subclass), and the filter would make it null.
            $trashedThreadOf = static function () use ($entityManager, $trashedId): Thread {
                $entityManager->getFilters()->disable('trash_filter');
                try {
                    return $entityManager->find(Thread::class, $trashedId);
                } finally {
                    $entityManager->getFilters()->enable('trash_filter');
                }
            };

            $compared = 0;
            foreach (HandWrittenQueries::scenarios($repository, $latest, \App\Entity\User::class, new \DateTime(), $trashedThreadOf()) as $name => [$before, $after]) {
                self::assertSame(HandWrittenQueries::normalise($before()), HandWrittenQueries::normalise($after()), $name.': the rows of before, in order');
                ++$compared;
            }
            self::assertGreaterThanOrEqual(30, $compared);
            // The rows written above are read by the scenarios that have them to read.
            self::assertSame(['/', '/about'], array_keys(array_filter(['/' => $views->countViews('/'), '/about' => $views->countViews('/about')])));
            self::assertSame('/new-page', $repository(\Base\Entity\Layout\Redirection::class)->forAddress('/old-page?x=1')?->getTarget(), 'SQLite compares the case of letters, unlike MySQL');
            self::assertCount(3, $repository(\Base\Entity\Layout\TextOverride::class)->map());
            self::assertCount(2, $repository(\Base\Entity\Extension\TrashBall::class)->expired());

            // Step E2b: a thread in the trash keeps its comments, which are not the visitors' book's - the case
            // the finder's join of the thread got wrong (Doctrine puts the trash filter in the join's ON).
            self::assertTrue($entityManager->getFilters()->isEnabled('trash_filter'), 'the trash filter is on');
            $comments = $repository(Comment::class);
            $ids = static fn (array $rows): array => array_map(static fn (Comment $comment) => $comment->getId(), $rows);
            $trashedThread = $trashedThreadOf();
            self::assertSame([$onTrashedId], $ids($comments->findVisible($trashedThread)));
            self::assertSame(1, $comments->countVisible($trashedThread));
            self::assertSame([$onLiveId], $ids($comments->findVisible($entityManager->find(Thread::class, $liveId))));
            self::assertContains($bookId, $ids($comments->findVisible(null)));
            self::assertNotContains($onTrashedId, $ids($comments->findVisible(null)));
            self::assertContains($onTrashedId, $ids($comments->findByThreadNullAndParentNull(['state' => CommentState::APPROVED])->getResult()), 'through the join, the trashed thread\'s comment reads as the book\'s');
            // The key criterion compiled on the comment's own foreign key, with no join.
            $query = $comments->findByState(CommentState::APPROVED, ['thread' => Key::of($trashedThread), 'parent' => Key::of(null)]);
            self::assertSame('SELECT e FROM '.Comment::class.' e WHERE e.thread = :key_thread AND e.parent IS NULL AND e.state = :state_0', $query->getDQL());
            self::assertSame($trashedThread, $query->getParameter('key_thread')?->getValue());
            self::assertSame('SELECT e FROM '.Comment::class.' e WHERE e.thread IS NOT NULL AND e.thread NOT IN(:key_thread_1)', $comments->findByThreadNotEqualToAndThreadNotEqualTo(Key::of(null), Key::of([$liveId]))->getDQL());
            self::assertSame(\count($comments->findAll()), \count($comments->findBy(['thread' => Key::of([])])), 'an empty list leaves the criterion out');
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    /**
     * What the name parses to: since F1 Finder\Parser's Query (ServiceEntityParser::read()); before,
     * the parser's own state after its __parse(), read by reflection (the column reset first: the
     * parser of before kept the previous call's).
     *
     * @param list<mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function parse(ServiceEntityRepository $repository, string $method, array $arguments): array
    {
        /** @var ServiceEntityParser $parser */
        $parser = (new \ReflectionProperty(ServiceEntityRepository::class, 'serviceParser'))->getValue($repository);

        try {
            if (method_exists($parser, 'read')) {
                $query = $parser->read($method, $arguments);
                $parse = ['method' => $query->method, 'arguments' => $query->arguments, 'criteria' => $query->criteria, 'options' => $query->options, 'separator' => $query->separator, 'column' => $query->column, 'cacheable' => $query->cacheable, 'eagerly' => $query->eagerly];
            } else {
                $property = static fn (string $name) => new \ReflectionProperty(ServiceEntityParser::class, $name);
                $property('column')->setValue($parser, null);
                try {
                    [$fn, $args] = (new \ReflectionMethod(ServiceEntityParser::class, '__parse'))->invoke($parser, $method, $arguments);
                    $parse = ['method' => $fn, 'arguments' => $args];
                    foreach (['criteria' => 'criteria', 'options' => 'options', 'separator' => 'operator', 'column' => 'column', 'cacheable' => 'cacheable', 'eagerly' => 'eagerly'] as $name => $field) {
                        $parse[$name] = $property($field)->getValue($parser);
                    }
                } finally {
                    $property('criteria')->setValue($parser, []);
                    $property('options')->setValue($parser, []);
                    $property('operator')->setValue($parser, null);
                    $property('cacheable')->setValue($parser, false);
                    $property('eagerly')->setValue($parser, false);
                }
            }
        } catch (\Throwable $e) {
            return ['exception' => $e::class, 'message' => self::message($e)];
        }

        return self::normalise($parse);
    }

    /**
     * The finder called: its Query (DQL, parameters, first and maximum results) where it hands one
     * back, else what it gave or threw.
     *
     * @param list<mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function call(ServiceEntityRepository $repository, string $method, array $arguments): array
    {
        try {
            $result = $repository->{$method}(...$arguments);
        } catch (\Throwable $e) {
            return ['exception' => $e::class, 'message' => self::message($e)];
        }

        if ($result instanceof Query) {
            $parameters = [];
            foreach ($result->getParameters() as $parameter) {
                $value = $parameter->getValue();
                // A bound the finder computes from now (YoungerThan, OlderThan, Within...) is kept as its
                // distance from now, to five minutes: the fixture was recorded at another moment.
                $parameters[$parameter->getName()] = $value instanceof \DateTimeInterface && $value->format(\DATE_ATOM) !== self::AT
                    ? ['@from_now' => 300 * (int) round(($value->getTimestamp() - time()) / 300)]
                    : self::normalise($value);
            }
            ksort($parameters);

            return ['dql' => $result->getDQL(), 'parameters' => $parameters, 'first' => $result->getFirstResult(), 'max' => $result->getMaxResults()];
        }

        return ['result' => self::normalise($result)];
    }

    /**
     * An exception's message without where it was raised (the line moved with the parser), the classes
     * moved to omnibridge/doctrine (step F3: the parser itself) named by their former names.
     */
    private static function message(\Throwable $e): string
    {
        return strtr((string) preg_replace('~, called in \S+ on line \d+~', '', $e->getMessage()), array_flip(\Base\Database\FormerNames::moved()));
    }

    /**
     * A query's DQL with ClassOf's subclasses in alphabetical order: the finder lists them as the root's discriminator
     * map does, in the order the mapping driver read the entity folders - the filesystem's, which differs from one disk
     * to another (APFS where the fixture was recorded; ext4's hashed directories, whose order follows each disk's hash
     * seed, on the CI's runner). The terms stay the same, only their order within the one AND (or OR) moves, which
     * changes nothing of what the query selects: each run of `AND NOT(e INSTANCE OF ...)` (ClassOf) or
     * `OR e INSTANCE OF ...` (^ClassOf, NotClassOf) is sorted, the class itself kept first.
     *
     * @param array<string, mixed>|string $query
     *
     * @return array<string, mixed>|string
     */
    private static function subclassesSorted(array|string $query): array|string
    {
        if (!\is_array($query) || !\is_string($query['dql'] ?? null)) {
            return $query;
        }
        $query['dql'] = preg_replace_callback('~(?: AND NOT\(e INSTANCE OF [\w\\\\]+\))+|(?: OR e INSTANCE OF [\w\\\\]+)+~', static function (array $run): string {
            preg_match_all('~ (?:AND NOT\(e INSTANCE OF [\w\\\\]+\)|OR e INSTANCE OF [\w\\\\]+)~', $run[0], $terms);
            $terms = $terms[0];
            sort($terms);

            return implode('', $terms);
        }, $query['dql']);

        return $query;
    }

    private static function normalise(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return ['@datetime' => $value->format(\DATE_ATOM)];
        }
        if ($value instanceof \BackedEnum) {
            return ['@enum' => $value::class, 'value' => $value->value];
        }
        if (\is_object($value)) {
            return ['@object' => $value::class] + (method_exists($value, 'getId') ? ['id' => $value->getId()] : []);
        }
        if (\is_array($value)) {
            return array_map(self::normalise(...), $value);
        }
        if (\is_float($value)) {
            return ['@float' => var_export($value, true)];
        }

        return $value;
    }
}
