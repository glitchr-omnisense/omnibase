<?php

namespace Base\Database\Lifecycle;

use App\Entity\Thread\Mention;
use App\Entity\User;
use Base\Entity\Thread;
use Base\Service\Model\Wysiwyg\MentionEnhancerInterface;
use Omnibridge\Persistence\Lifecycle;
use Omnibridge\Persistence\UnitOfWork;

/**
 * Who takes part in a thread besides its owners, on omnisense/omnibridge's neutral lifecycle events
 * (plan "omnibase agnostic"): the followers of a reply and the users its text mentions.
 *
 * - A reply (a thread with a parent) is followed by its parent's owners, from its creation (PrePersist)
 *   and when it is moved under another parent (follow(), which the persistence calls at its flush:
 *   a collection changed while an update is written is not written).
 * - The users a thread's text mentions (<mention> tags, in every translation) each have one Mention,
 *   kept in step at every flush that writes the text (OnFlush): a mention added to the text gets one,
 *   persisted in the running flush; one taken out of it loses its own, removed in the same flush.
 *   ThreadSubscriber::onPublishable() mails them at the thread's publication.
 *
 * Base\EntitySubscriber\ThreadSubscriber relays Doctrine's events to it and lists the threads a flush
 * writes the text of (the neutral OnFlush carries no list of entities).
 */
final class ThreadParticipants
{
    /**
     * @param \Closure(): MentionEnhancerInterface $mentionEnhancer lazy: Doctrine builds its listeners when
     *                                                              the event manager initialises, and the enhancer's repositories read the mapping
     */
    public function __construct(private readonly \Closure $mentionEnhancer)
    {
    }

    public function onPrePersist(Lifecycle\PrePersist $event): void
    {
        if ($event->entity instanceof Thread) {
            $this->follow($event->entity);
        }
    }

    /**
     * The mentions of the threads whose text this flush writes, made to match their text.
     *
     * @param iterable<Thread> $threads
     */
    public function onFlush(Lifecycle\OnFlush $event, iterable $threads): void
    {
        foreach ($threads as $thread) {
            $this->mentions($thread, $event->unitOfWork);
        }
    }

    /** A reply followed by its parent's owners; whether it gained a follower. */
    public function follow(Thread $thread): bool
    {
        $parent = $thread->getParent();
        if (!$parent) {
            return false;
        }

        $followed = false;
        foreach ($parent->getOwners() as $owner) {
            /** @var User $owner */
            if ($owner && !$thread->getFollowers()->contains($owner)) {
                $thread->addFollower($owner);
                $followed = true;
            }
        }

        return $followed;
    }

    /**
     * The users the thread's text mentions, in every translation (an editor's JSON read by its leaves).
     *
     * @return User[]
     */
    public function mentionees(Thread $thread): array
    {
        $mentionees = [];
        foreach ($thread->getTranslations() as $translation) {
            if (!method_exists($translation, 'getContent')) {
                continue;
            }

            $content = $translation->getContent();
            $content = is_string($content) && is_json($content) ? json_leaves($content) : $content;
            foreach (($this->mentionEnhancer)()->extractMentionees($content) as $mentionee) {
                $mentionees[$this->key($mentionee)] = $mentionee;
            }
        }

        return array_values($mentionees);
    }

    /**
     * One Mention per user the text mentions: the missing ones persisted, those the text no longer has
     * removed. The thread's own collection is read, not a (cached) query: a mention removed a flush ago
     * is not handed back.
     */
    private function mentions(Thread $thread, UnitOfWork $unitOfWork): void
    {
        $mentionees = [];
        foreach ($this->mentionees($thread) as $mentionee) {
            $mentionees[$this->key($mentionee)] = $mentionee;
        }

        $kept = [];
        foreach ($thread->getMentions()->toArray() as $mention) {
            /** @var Mention $mention */
            $key = $mention->getMentionee() ? $this->key($mention->getMentionee()) : null;
            if (null !== $key && isset($mentionees[$key]) && !isset($kept[$key])) {
                $kept[$key] = true;
                continue;
            }

            $thread->removeMention($mention);
            if ($unitOfWork->contains($mention)) {
                $unitOfWork->remove($mention);
            }
        }

        foreach (array_diff_key($mentionees, $kept) as $mentionee) {
            $mention = new Mention($mentionee, $thread);
            foreach ($thread->getOwners() as $mentioner) {
                /** @var User $mentioner */
                if ($mentioner) {
                    $mention->addMentioner($mentioner);
                }
            }

            $thread->addMention($mention);
            $unitOfWork->persist($mention);
            $unitOfWork->recompute($mention);
        }
    }

    private function key(User $user): string
    {
        return null !== $user->getId() ? 'id:'.$user->getId() : 'object:'.spl_object_id($user);
    }
}
