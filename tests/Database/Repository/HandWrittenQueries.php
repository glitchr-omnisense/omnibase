<?php

namespace Tests\Base\Database\Repository;

use Base\Entity\Analytics\PageView;
use Base\Entity\Analytics\Visit;
use Base\Entity\Extension\Revision;
use Base\Entity\Extension\TrashBall;
use Base\Entity\Hours\ScopedWeek;
use Base\Entity\Hours\SpecialDay;
use Base\Entity\Layout\Redirection;
use Base\Entity\Layout\TextOverride;
use Base\Entity\Thread;
use Base\Entity\Thread\Comment;
use Base\Entity\Thread\Tag;
use Base\Entity\ThreadIntl;
use Base\Entity\User\Complaint;
use Base\Entity\User\Connection;
use Base\Entity\User\Identity;
use Base\Entity\User\Notification;
use Base\Entity\User\Passkey;
use Base\Entity\User\PushSubscription;
use Base\Enum\CommentState;
use Base\Enum\ThreadState;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

/**
 * The repositories' hand-written queries as they were before step E2 of the plan "omnibase
 * agnostic" (omnibridge/doctrine 6e3728ec), word for word, beside the methods that replaced them
 * with the finder language: FinderParityTest runs both on the same rows and compares what they
 * give, in order (R12: a rewritten query must not change its rows silently). lesringostars'
 * FinderParityTest holds a copy, run on the site's data.
 *
 * Not here: the methods declared #[BackendOnly('doctrine')] (native SQL, a bulk UPDATE), which
 * keep their query, and Comments::findVisible()/countVisible(), not rewritten (see the plan's
 * inventory, ~/Sites/.agent-briefs/e2/inventory.md).
 */
final class HandWrittenQueries
{
    /**
     * Each rewritten method: [before, after], two callables of the same arguments.
     *
     * @param \Closure(class-string): EntityRepository $repository
     * @param \Closure(class-string): object|null      $latest     the newest row of a class, null without one
     *
     * @return array<string, array{\Closure(): mixed, \Closure(): mixed}>
     */
    public static function scenarios(\Closure $repository, \Closure $latest, string $userClass, ?\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTime();
        $qb = static fn (string $class, string $alias) => $repository($class)->createQueryBuilder($alias);
        $user = $latest($userClass);
        $thread = $latest(Thread::class);
        $trashBall = $latest(TrashBall::class);
        $revision = $latest(Revision::class);
        $identity = $latest(Identity::class);
        $passkey = $latest(Passkey::class);
        $redirection = $latest(Redirection::class);
        $scopedWeek = $latest(ScopedWeek::class);
        $since = \DateTimeImmutable::createFromInterface($now)->modify('-30 days');

        $scenarios = [
            'TrashBalls::contents' => [
                fn () => $qb(TrashBall::class, 't')->orderBy('t.id', 'DESC')->setFirstResult(0)->getQuery()->getResult(),
                fn () => $repository(TrashBall::class)->contents(),
            ],
            'TrashBalls::contents(class, 5, 1)' => [
                fn () => $qb(TrashBall::class, 't')->orderBy('t.id', 'DESC')->setFirstResult(1)->andWhere('t.entityClass = :class')->setParameter('class', $trashBall?->getEntityClass() ?? Thread::class)->setMaxResults(5)->getQuery()->getResult(),
                fn () => $repository(TrashBall::class)->contents($trashBall?->getEntityClass() ?? Thread::class, 5, 1),
            ],
            'TrashBalls::countContents' => [
                fn () => (int) $qb(TrashBall::class, 't')->select('COUNT(t.id)')->getQuery()->getSingleScalarResult(),
                fn () => $repository(TrashBall::class)->countContents(),
            ],
            'TrashBalls::countContents(class)' => [
                fn () => (int) $qb(TrashBall::class, 't')->select('COUNT(t.id)')->andWhere('t.entityClass = :class')->setParameter('class', $trashBall?->getEntityClass() ?? Thread::class)->getQuery()->getSingleScalarResult(),
                fn () => $repository(TrashBall::class)->countContents($trashBall?->getEntityClass() ?? Thread::class),
            ],
            'TrashBalls::expired' => [
                fn () => $qb(TrashBall::class, 't')->andWhere('t.permanentAfter IS NOT NULL')->andWhere('t.permanentAfter <= :now')->setParameter('now', $now)->orderBy('t.permanentAfter', 'ASC')->getQuery()->getResult(),
                fn () => $repository(TrashBall::class)->expired($now),
            ],
            'TrashBalls::expired(in a year, 3)' => [
                fn () => $qb(TrashBall::class, 't')->andWhere('t.permanentAfter IS NOT NULL')->andWhere('t.permanentAfter <= :now')->setParameter('now', (clone $now)->modify('+1 year'))->orderBy('t.permanentAfter', 'ASC')->setMaxResults(3)->getQuery()->getResult(),
                fn () => $repository(TrashBall::class)->expired((clone $now)->modify('+1 year'), 3),
            ],
            'TrashBalls::forEntity' => [
                fn () => $qb(TrashBall::class, 't')->andWhere('t.entityClass = :class')->setParameter('class', $trashBall?->getEntityClass() ?? Thread::class)->andWhere('t.entityId = :id')->setParameter('id', $trashBall?->getEntityId() ?? 1)->orderBy('t.id', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult(),
                fn () => $repository(TrashBall::class)->forEntity($trashBall?->getEntityClass() ?? Thread::class, (int) ($trashBall?->getEntityId() ?? 1)),
            ],
            'Revisions::history' => [
                fn () => $qb(Revision::class, 'r')->andWhere('r.entityClass = :class')->setParameter('class', $revision?->getEntityClass() ?? Thread::class)->andWhere('r.entityId = :id')->setParameter('id', $revision?->getEntityId() ?? 1)->orderBy('r.id', 'DESC')->setMaxResults(10)->getQuery()->getResult(),
                fn () => $repository(Revision::class)->history($revision?->getEntityClass() ?? Thread::class, (int) ($revision?->getEntityId() ?? 1), 10),
            ],
            'Revisions::obsoleteIds' => [
                fn () => array_map('intval', array_column($qb(Revision::class, 'r')->select('r.id')->andWhere('r.entityClass = :class')->setParameter('class', $revision?->getEntityClass() ?? Thread::class)->andWhere('r.entityId = :id')->setParameter('id', $revision?->getEntityId() ?? 1)->orderBy('r.id', 'DESC')->setFirstResult(1)->setMaxResults(1000)->getQuery()->getScalarResult(), 'id')),
                fn () => $repository(Revision::class)->obsoleteIds($revision?->getEntityClass() ?? Thread::class, (int) ($revision?->getEntityId() ?? 1), 1),
            ],
            'ScopedWeeks::ofScope' => [
                fn () => $qb(ScopedWeek::class, 'w')->andWhere('w.scope = :scope')->setParameter('scope', $scopedWeek?->getScope() ?? 'none')->setMaxResults(1)->getQuery()->getOneOrNullResult(),
                fn () => $repository(ScopedWeek::class)->ofScope($scopedWeek?->getScope() ?? 'none'),
            ],
            'SpecialDays::upcoming' => [
                fn () => $qb(SpecialDay::class, 's')->andWhere('s.endsOn >= :from')->setParameter('from', $since->format('Y-m-d'))->orderBy('s.startsOn', 'ASC')->setMaxResults(50)->andWhere('s.scope IS NULL')->getQuery()->getResult(),
                fn () => $repository(SpecialDay::class)->upcoming($since),
            ],
            'SpecialDays::upcoming(scope)' => [
                fn () => $qb(SpecialDay::class, 's')->andWhere('s.endsOn >= :from')->setParameter('from', $since->format('Y-m-d'))->orderBy('s.startsOn', 'ASC')->setMaxResults(50)->andWhere('s.scope = :scope')->setParameter('scope', 'office')->getQuery()->getResult(),
                fn () => $repository(SpecialDay::class)->upcoming($since, 50, 'office'),
            ],
            'Redirections::forAddress' => [
                fn () => self::forAddress($qb, $redirection?->getSource() ?? '/nowhere'),
                fn () => $repository(Redirection::class)->forAddress($redirection?->getSource() ?? '/nowhere'),
            ],
            'Redirections::forAddress(upper case, query)' => [
                fn () => self::forAddress($qb, mb_strtoupper($redirection?->getSource() ?? '/nowhere').'?a=1'),
                fn () => $repository(Redirection::class)->forAddress(mb_strtoupper($redirection?->getSource() ?? '/nowhere').'?a=1'),
            ],
            'TextOverrides::map' => [
                fn () => self::map($qb),
                fn () => $repository(TextOverride::class)->map(),
            ],
            'Comments::countPending' => [
                fn () => (int) $qb(Comment::class, 'c')->select('COUNT(c.id)')->andWhere('c.state = :pending')->setParameter('pending', CommentState::PENDING)->getQuery()->getSingleScalarResult(),
                fn () => $repository(Comment::class)->countPending(),
            ],
            'Threads::byStates' => [
                fn () => $qb(Thread::class, 'e')->andWhere('e.state IN (:states)')->setParameter('states', [ThreadState::PUBLISH])->getQuery()->getResult(),
                fn () => $repository(Thread::class)->byStates([ThreadState::PUBLISH]),
            ],
            'ThreadTranslations::search' => [
                fn () => self::search($qb, ['title' => 'a', 'content' => 'e'], [ThreadState::PUBLISH], null),
                fn () => $repository(ThreadIntl::class)->search(['title' => 'a', 'content' => 'e'], [ThreadState::PUBLISH]),
            ],
            'ThreadTranslations::search(no state)' => [
                fn () => self::search($qb, ['excerpt' => 'o'], [], null),
                fn () => $repository(ThreadIntl::class)->search(['excerpt' => 'o']),
            ],
            'ThreadTranslations::search(parent)' => [
                fn () => self::search($qb, ['title' => 'e'], [ThreadState::PUBLISH, ThreadState::DRAFT], $thread?->getParent() ?? $thread),
                fn () => $repository(ThreadIntl::class)->search(['title' => 'e'], [ThreadState::PUBLISH, ThreadState::DRAFT], $thread?->getParent() ?? $thread),
            ],
            'Complaints::newest' => [
                fn () => $qb(Complaint::class, 'c')->orderBy('c.createdAt', 'DESC')->setMaxResults(200)->getQuery()->getResult(),
                fn () => $repository(Complaint::class)->newest(),
            ],
            'Complaints::newest(open)' => [
                fn () => $qb(Complaint::class, 'c')->orderBy('c.createdAt', 'DESC')->setMaxResults(200)->andWhere('c.status = :status')->setParameter('status', Complaint::OPEN)->getQuery()->getResult(),
                fn () => $repository(Complaint::class)->newest(Complaint::OPEN),
            ],
            'PushSubscriptions::countDistinctUsers' => [
                fn () => (int) $qb(PushSubscription::class, 's')->select('COUNT(DISTINCT s.user)')->getQuery()->getSingleScalarResult(),
                fn () => $repository(PushSubscription::class)->countDistinctUsers(),
            ],
            'PageViews::countViews' => [
                fn () => (int) $qb(PageView::class, 'pv')->select('COALESCE(SUM(pv.views), 0)')->getQuery()->getSingleScalarResult(),
                fn () => $repository(PageView::class)->countViews(),
            ],
            'PageViews::countViews(/, since, human)' => [
                fn () => (int) $qb(PageView::class, 'pv')->select('COALESCE(SUM(pv.views), 0)')->andWhere('pv.path = :path')->setParameter('path', '/')->andWhere('pv.date >= :since')->setParameter('since', $since)->andWhere('pv.source = :source')->setParameter('source', PageView::SOURCE_HUMAN)->getQuery()->getSingleScalarResult(),
                fn () => $repository(PageView::class)->countViews('/', $since, PageView::SOURCE_HUMAN),
            ],
            'PageViews::earliestDate' => [
                fn () => ($date = $qb(PageView::class, 'pv')->select('MIN(pv.date)')->getQuery()->getSingleScalarResult()) ? new \DateTimeImmutable(substr((string) $date, 0, 10), new \DateTimeZone('UTC')) : null,
                fn () => $repository(PageView::class)->earliestDate(),
            ],
            'Visits::countUnique' => [
                fn () => (int) $qb(Visit::class, 'v')->select('COUNT(DISTINCT v.subjectId)')->andWhere('v.subjectType = :type')->setParameter('type', Visit::TYPE_USER)->getQuery()->getSingleScalarResult(),
                fn () => $repository(Visit::class)->countUnique(Visit::TYPE_USER),
            ],
            'Visits::countUnique(since)' => [
                fn () => (int) $qb(Visit::class, 'v')->select('COUNT(DISTINCT v.subjectId)')->andWhere('v.subjectType = :type')->setParameter('type', Visit::TYPE_VISITOR)->andWhere('v.date >= :since')->setParameter('since', $since)->getQuery()->getSingleScalarResult(),
                fn () => $repository(Visit::class)->countUnique(Visit::TYPE_VISITOR, $since),
            ],
            'Passkeys::findOneByCredential' => [
                fn () => $qb(Passkey::class, 'passkey')->where('passkey.publicKeyCredentialId = :id')->setParameter('id', $passkey?->publicKeyCredentialId ?? 'none', \Omnibridge\Doctrine\Omnibase\Database\Type\Passkey\Base64::NAME)->getQuery()->getOneOrNullResult(),
                fn () => $repository(Passkey::class)->findOneByCredential($passkey?->publicKeyCredentialId ?? 'none'),
            ],
            'Identities::findOneBySubject' => [
                fn () => $qb(Identity::class, 'identity')->where('identity.provider = :provider')->andWhere('identity.subject = :subject')->setParameter('provider', $identity?->getProvider() ?? 'none')->setParameter('subject', $identity?->getSubject() ?? 'none')->getQuery()->getOneOrNullResult(),
                fn () => $repository(Identity::class)->findOneBySubject($identity?->getProvider() ?? 'none', $identity?->getSubject() ?? 'none'),
            ],
            'Tags::findByInsensitiveIdentifier' => [
                fn () => self::insensitive($qb, Tag::class, 'E', ['slug']),
                fn () => $repository(Tag::class)->findByInsensitiveIdentifier('E')->getResult(),
            ],
            'Threads::findByInsensitiveIdentifier(slug, state)' => [
                fn () => self::insensitive($qb, Thread::class, 'A%', ['slug', 'state']),
                fn () => $repository(Thread::class)->findByInsensitiveIdentifier('A%', ['slug', 'state'])->getResult(),
            ],
        ];

        if (null !== $user) {
            $scenarios += [
                'Connections::countFor' => [
                    fn () => (int) $qb(Connection::class, 'c')->select('COUNT(c.id)')->andWhere('c.user = :user')->setParameter('user', $user)->getQuery()->getSingleScalarResult(),
                    fn () => $repository(Connection::class)->countFor($user),
                ],
                'Identities::findOfUser' => [
                    fn () => $qb(Identity::class, 'identity')->where('identity.user = :user')->setParameter('user', $identity?->getUser() ?? $user)->orderBy('identity.provider')->getQuery()->getResult(),
                    fn () => $repository(Identity::class)->findOfUser($identity?->getUser() ?? $user),
                ],
                'Notifications::countUnreadFor' => [
                    fn () => (int) self::visibleFor($qb, $user)->select('COUNT(n.id)')->andWhere('n.isRead = false')->getQuery()->getSingleScalarResult(),
                    fn () => $repository(Notification::class)->countUnreadFor($user),
                ],
                'Notifications::findVisibleFor' => [
                    fn () => self::visibleFor($qb, $user)->orderBy('n.sentAt', 'DESC')->addOrderBy('n.id', 'DESC')->setMaxResults(100)->setFirstResult(0)->getQuery()->getResult(),
                    fn () => $repository(Notification::class)->findVisibleFor($user),
                ],
                'Notifications::findVisibleFor(unread, 5, 1)' => [
                    fn () => self::visibleFor($qb, $user)->andWhere('n.isRead = false')->orderBy('n.sentAt', 'DESC')->addOrderBy('n.id', 'DESC')->setMaxResults(5)->setFirstResult(1)->getQuery()->getResult(),
                    fn () => $repository(Notification::class)->findVisibleFor($user, 5, 1, true),
                ],
                'Users::byUsernameContaining' => [
                    fn () => $qb($userClass, 'u')->where('LOWER(u.username) LIKE :term')->setParameter('term', '%'.addcslashes(mb_strtolower(mb_substr((string) $user->getUsername(), 0, 2)), '%_').'%')->orderBy('u.username', 'ASC')->setMaxResults(50)->getQuery()->getResult(),
                    fn () => $repository($userClass)->byUsernameContaining(mb_substr((string) $user->getUsername(), 0, 2)),
                ],
                'Users::findActiveAdmins' => [
                    fn () => self::activeAdmins($qb, $userClass, 10 * 365 * 86400, null),
                    fn () => $repository($userClass)->findActiveAdmins(10 * 365 * 86400),
                ],
                'Users::findActiveAdmins(but one)' => [
                    fn () => self::activeAdmins($qb, $userClass, 10 * 365 * 86400, $user->getId()),
                    fn () => $repository($userClass)->findActiveAdmins(10 * 365 * 86400, $user->getId()),
                ],
                'Users::findByInsensitiveIdentifier' => [
                    fn () => self::insensitive($qb, $userClass, mb_substr((string) $user->getUserIdentifier(), 0, 3), [$userClass::getUserIdentifierField()]),
                    fn () => $repository($userClass)->findByInsensitiveIdentifier(mb_substr((string) $user->getUserIdentifier(), 0, 3))->getResult(),
                ],
            ];
        }

        return $scenarios;
    }

    private static function forAddress(\Closure $qb, string $address): ?Redirection
    {
        $path = strtok($address, '?') ?: '/';
        $candidates = array_values(array_unique([$address, $path]));
        $exact = $qb(Redirection::class, 'r')->andWhere('r.enabled = true')->andWhere('r.source IN (:sources)')->setParameter('sources', $candidates)->getQuery()->getResult();
        foreach ($candidates as $candidate) {
            foreach ($exact as $redirection) {
                if ($redirection->getSource() === $candidate) {
                    return $redirection;
                }
            }
        }
        foreach ($candidates as $candidate) {
            foreach ($exact as $redirection) {
                if (mb_strtolower($redirection->getSource()) === mb_strtolower($candidate)) {
                    return $redirection;
                }
            }
        }
        $prefixes = $qb(Redirection::class, 'r')->andWhere('r.enabled = true')->andWhere('r.source LIKE :star')->setParameter('star', '%*')->getQuery()->getResult();
        usort($prefixes, static fn (Redirection $a, Redirection $b) => \strlen($b->getSource()) <=> \strlen($a->getSource()));
        foreach ($prefixes as $redirection) {
            if (null !== $redirection->resolve($address)) {
                return $redirection;
            }
        }

        return null;
    }

    private static function map(\Closure $qb): array
    {
        $map = [];
        foreach ($qb(TextOverride::class, 't')->select('t.domain, t.locale, t.key, t.value')->getQuery()->getArrayResult() as $row) {
            $map[$row['domain'].'|'.$row['locale'].'|'.$row['key']] = $row['value'];
        }

        return $map;
    }

    private static function search(\Closure $qb, array $terms, array $states, mixed $parent): array
    {
        $queryBuilder = $qb(ThreadIntl::class, 'i')->join('i.translatable', 't');
        $expr = [];
        foreach ($terms as $field => $term) {
            $expr[] = 'LOWER(i.'.$field.') LIKE :term_'.$field;
            $queryBuilder->setParameter('term_'.$field, '%'.mb_strtolower(trim($term)).'%');
        }
        $queryBuilder->andWhere(implode(' OR ', $expr));
        if ($states) {
            $queryBuilder->andWhere('t.state IN (:states)')->setParameter('states', $states);
        }
        if ($parent) {
            $queryBuilder->andWhere('t.parent = :parent')->setParameter('parent', $parent);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private static function visibleFor(\Closure $qb, object $user): \Doctrine\ORM\QueryBuilder
    {
        return $qb(Notification::class, 'n')
            ->andWhere('n.user = :user')->setParameter('user', $user)
            ->andWhere("COALESCE(n.title, '') <> '' OR COALESCE(n.subject, '') <> '' OR COALESCE(n.content, '') <> ''");
    }

    private static function activeAdmins(\Closure $qb, string $userClass, int $onlineDelay, ?int $excludeId): array
    {
        $query = $qb($userClass, 'u')
            ->andWhere('u.activeAt > :threshold')
            ->andWhere('u.roles LIKE :roleAdmin OR u.roles LIKE :roleSuperadmin')
            ->setParameter('threshold', new \DateTime($onlineDelay.' seconds ago'))
            ->setParameter('roleAdmin', '%ROLE_ADMIN%')
            ->setParameter('roleSuperadmin', '%ROLE_SUPERADMIN%')
            ->orderBy('u.activeAt', 'DESC');
        if (null !== $excludeId) {
            $query->andWhere('u.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $query->getQuery()->getResult();
    }

    private static function insensitive(\Closure $qb, string $class, string $identifier, array $fields): array
    {
        $identifier = preg_replace('/%$/', '', $identifier);
        $query = $qb($class, 'u')->setParameter('identifier', '%'.strtolower($identifier).'%');
        foreach ($fields as $field) {
            $query->orWhere('LOWER(u.'.$field.') LIKE :identifier');
        }

        return $query->getQuery()->getResult();
    }

    /** What a scenario gave, comparable: entities by class and id, dates as text, the rest as it is. */
    public static function normalise(mixed $value): mixed
    {
        if ($value instanceof Query) {
            $value = $value->getResult();
        }
        if ($value instanceof \DateTimeInterface) {
            return ['@datetime' => $value->format(\DATE_ATOM)];
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if (\is_object($value)) {
            $class = $value instanceof \Doctrine\Persistence\Proxy ? get_parent_class($value) : $value::class;

            return ['@entity' => $class, 'id' => method_exists($value, 'getId') ? $value->getId() : spl_object_id($value)];
        }
        if (\is_array($value)) {
            return array_map(self::normalise(...), $value);
        }

        return $value;
    }
}
