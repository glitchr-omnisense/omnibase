<?php

namespace Tests\Base\Database\Lifecycle;

use Base\Entity\Thread;
use Base\Entity\Thread\Mention;
use Base\Enum\ThreadState;
use Base\Entity\User\Notification;
use Base\Notifier\NotifierInterface;
use Base\Service\Locator;
use Base\Service\ObfuscatorInterface;
use Base\Database\UnitOfWorks;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Database\Backend\BothBackends;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\Http\HttpTestTrait;

/**
 * ThreadSubscriber's Doctrine hooks (prePersist, onFlush), relayed to ThreadParticipants: they were
 * registered nowhere, so no Mention was stored, thread.mentioned never mailed, and no reply was
 * followed by its parent's owners. On both persistences (BothBackends, plan "omnibase agnostic", step F7c:
 * omnibridge/eloquent's ThreadListener and entity dispatchers on the same rule); the users are Doctrine's, read
 * through the backend.
 */
final class ThreadParticipantsTest extends KernelTestCase
{
    use HttpTestTrait, BothBackends {
        HttpTestTrait::entityManager insteadof BothBackends;
    }

    private string $backend = 'doctrine';

    private function uow(): UnitOfWork
    {
        return $this->unitOfWork($this->backend);
    }

    /** @var Thread[] */
    private array $threads = [];

    protected function setUp(): void
    {
        $this->bootHost();
        static::getContainer()->get('request_stack')->push(Request::create('/'));
    }

    protected function tearDown(): void
    {
        // Purged (removed twice: the first removal puts a thread in the bin), so that the users can go.
        $em = $this->entityManager();
        $em->clear();
        $em->getFilters()->isEnabled('trash_filter') && $em->getFilters()->disable('trash_filter');
        foreach (array_reverse($this->threads) as $thread) {
            for ($i = 0; $i < 2; ++$i) {
                $managed = $em->find(Thread::class, $thread->getId());
                if ($managed) {
                    $em->remove($managed);
                    $em->flush();
                }
            }
        }
        $this->threads = [];

        $this->removeUsers();
        parent::tearDown();
    }

    /** @dataProvider backends */
    public function testAMentionInTheTextIsStoredAtTheFlushAndRemovedWithIt(string $backend): void
    {
        $this->backend = $backend;
        $author = $this->createUser();
        $mentionee = $this->createUser();

        $thread = $this->thread($author, 'Bonjour '.$this->mention($mentionee).' !');
        self::assertSame([$mentionee->getId()], $this->mentioneeIds($thread), 'one Mention for the user mentioned');

        // Saved again with the same text, or another mention of the same user: still one.
        $thread->setContent('Re-bonjour '.$this->mention($mentionee).' et '.$this->mention($mentionee));
        $this->uow()->flush();
        self::assertSame([$mentionee->getId()], $this->mentioneeIds($thread));

        $thread->setContent('Plus de mention.');
        $this->uow()->flush();
        self::assertSame([], $this->mentioneeIds($thread), 'the text no longer mentions them: their Mention is removed');
    }

    /** @dataProvider backends */
    public function testTheMentionerIsTheOwner(string $backend): void
    {
        $this->backend = $backend;
        $author = $this->createUser();
        $mentionee = $this->createUser();
        $thread = $this->thread($author, $this->mention($mentionee));

        $this->clear($backend);
        $mention = UnitOfWorks::repositories($this->uow())->of(Mention::class)->findOneBy(['thread' => $thread->getId()]);
        self::assertNotNull($mention);
        self::assertSame([$author->getId()], array_map(fn ($user) => $user->getId(), $mention->getMentioners()->toArray()));
    }

    /** @dataProvider backends */
    public function testAReplyIsFollowedByItsParentsOwners(string $backend): void
    {
        $this->backend = $backend;
        $author = $this->createUser();
        $replier = $this->createUser();
        $parent = $this->thread($author, 'Le fil');

        $reply = $this->thread($replier, 'Une réponse', $this->find($backend, Thread::class, $parent->getId()));
        self::assertSame([$author->getId()], $this->followerIds($reply));

        // Moved under another parent: its owners follow it too.
        $owner = $this->createUser();
        $other = $this->thread($owner, 'Un autre fil');
        $this->find($backend, Thread::class, $reply->getId())->setParent($this->find($backend, Thread::class, $other->getId()));
        $this->uow()->flush();

        $followers = $this->followerIds($reply);
        sort($followers);
        $expected = [$author->getId(), $owner->getId()];
        sort($expected);
        self::assertSame($expected, $followers);
    }

    /** @dataProvider backends */
    public function testThePublicationMailsTheMentioneeAndNotAnAuthorMentionedInTheirOwnThread(string $backend): void
    {
        $this->backend = $backend;
        $author = $this->createUser();
        $mentionee = $this->createUser();

        $thread = $this->thread($author, 'Avec '.$this->mention($mentionee).' et moi-même, '.$this->mention($author).'.');
        self::assertCount(2, $this->mentioneeIds($thread));

        $sent = $this->publish($thread);

        sort($sent);
        $expected = [[$author->getId(), 'publish.html.twig'], [$mentionee->getId(), 'mention.html.twig']];
        sort($expected);
        self::assertSame($expected, $sent, 'the author is told of the publication, not of their own mention; the mentionee of theirs');
    }

    /** @dataProvider backends */
    public function testAThreadWrittenBeforeItsMentionsWereStoredMailsThemOnceAtItsPublication(string $backend): void
    {
        $this->backend = $backend;
        $author = $this->createUser();
        $mentionee = $this->createUser();

        $thread = $this->thread($author, 'Écrit avant : '.$this->mention($mentionee));
        // Its Mention taken out, as for a thread written while the hooks were registered nowhere.
        $metadata = $this->entityManager()->getClassMetadata(Mention::class);
        $this->entityManager()->getConnection()->executeStatement(sprintf('DELETE FROM %s WHERE %s = ?', $metadata->getTableName(), $metadata->getSingleAssociationJoinColumnName('thread')), [$thread->getId()]);
        $this->clear($backend);
        self::assertSame([], $this->mentioneeIds($thread));

        $sent = $this->publish($thread, again: true);

        self::assertSame([$mentionee->getId()], $this->mentioneeIds($thread), 'its Mention stored at the flush that publishes it');
        self::assertSame([[$mentionee->getId(), 'mention.html.twig']], array_values(array_filter($sent, fn (array $mail) => 'mention.html.twig' === $mail[1])), 'mailed once, and not again at the next save');
    }

    /**
     * The thread made publishable and flushed (thread.publishable publishes it), then saved once more when
     * $again; what was notified, to whom. The channels are the notifier's policy (the harness's turns every
     * e-mail into a flash), so the notifier BaseTrait hands to the notifications is a spy here.
     *
     * @return list<array{0: ?int, 1: string}>
     */
    private function publish(Thread $thread, bool $again = false): array
    {
        $sent = [];
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->method('sendUsers')->willReturnCallback(function (Notification $notification) use (&$sent, $notifier) {
            $sent[] = [$notification->getUser()?->getId(), basename((string) $notification->getHtmlTemplate())];

            return $notifier;
        });
        $property = new \ReflectionProperty(Locator::class, 'notifier');
        $previous = $property->getValue();
        $property->setValue(null, $notifier);

        try {
            $em = $this->uow();
            $thread = $this->find($this->backend, Thread::class, $thread->getId());
            $thread->setState(ThreadState::FUTURE);
            $thread->setPublishedAt(new \DateTime('-1 hour'));
            $em->flush();
            self::assertSame(ThreadState::PUBLISH, $thread->getState(), 'thread.publishable handled');

            if ($again) {
                $thread->poke();
                $thread->setContent($thread->getContent().' ');
                $em->flush();
            }
        } finally {
            $property->setValue(null, $previous);
        }

        return $sent;
    }

    private function thread(object $owner, string $content, ?Thread $parent = null): Thread
    {
        $owner = $this->find($this->backend, $owner::class, $owner->getId());
        $thread = new Thread($owner, $parent, 'Fil '.bin2hex(random_bytes(3)), 'fil-'.bin2hex(random_bytes(4)));
        $thread->setContent($content);

        $em = $this->uow();
        $em->persist($thread);
        $em->flush();

        return $this->threads[] = $thread;
    }

    private function mention(object $user): string
    {
        $hash = static::getContainer()->get('obfuscator')->encode(['id' => $user->getId(), 'className' => $user::class], ObfuscatorInterface::NO_SHORT);

        return '<mention class="ce-mention" data-json=\''.json_encode(['value' => $hash]).'\'>@'.$user.'</mention>';
    }

    /** @return int[] read from the database */
    private function mentioneeIds(Thread $thread): array
    {
        $metadata = $this->entityManager()->getClassMetadata(Mention::class);
        $mentionee = $metadata->getSingleAssociationJoinColumnName('mentionee');

        return array_map('intval', array_column($this->entityManager()->getConnection()->fetchAllAssociative(
            sprintf('SELECT %1$s AS user_id FROM %2$s WHERE %3$s = ? ORDER BY %1$s', $mentionee, $metadata->getTableName(), $metadata->getSingleAssociationJoinColumnName('thread')), [$thread->getId()],
        ), 'user_id'));
    }

    /** @return int[] read from the database */
    private function followerIds(Thread $thread): array
    {
        $em = $this->entityManager();
        $table = $em->getClassMetadata(Thread::class)->getAssociationMapping('followers')->joinTable;

        return array_map('intval', array_column($em->getConnection()->fetchAllAssociative(
            sprintf('SELECT %s AS user_id FROM %s WHERE %s = ?', $table->inverseJoinColumns[0]->name, $table->name, $table->joinColumns[0]->name), [$thread->getId()],
        ), 'user_id'));
    }
}
