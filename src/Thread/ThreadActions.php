<?php

namespace Base\Thread;

use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\RowLocks;
use Base\Entity\Thread;
use Base\Entity\User;
use Base\Enum\ThreadState;
use Base\Enum\UserRole;
use Base\Repository\Thread\Likes;
use Base\Repository\Threads;
use Base\Screen\Json;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Requests;
use Omnibridge\Contract\Tokens;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The threads' API (/api/thread/{slug}/...), written once for every host (plan "omnibase agnostic", step F5e,
 * slice 5b; formerly Api\ThreadController): a thread published or hidden by an administrator, followed or no longer
 * followed by a member, liked or no longer liked - each by the "api_thread" token (the body's _token or an
 * X-CSRF-Token header: as plain GET routes, a link or a picture elsewhere published a thread for a signed-in
 * administrator, and made anyone like it). Each answers the JSON the controller answered, statuses included (a
 * visitor's "Unauthorized" carries 401 in its body under a 200, as it did); null is no such thread (404).
 */
final class ThreadActions
{
    public const TOKEN = 'api_thread';

    /** @param Threads<Thread> $threads */
    public function __construct(
        private readonly Access $access,
        private readonly Tokens $tokens,
        private readonly UnitOfWork $unitOfWork,
        private readonly Threads $threads,
        private readonly Likes $likes,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?Requests $requests = null,
    ) {
    }

    /** The refusal of a request without the token, null with it. */
    public function denied(?string $token): ?Json
    {
        return $this->tokens->isValid(self::TOKEN, $token) ? null : new Json(['code' => 403, 'response' => 'Invalid token'], 403);
    }

    public function publish(string $slug, ?string $token): ?Json
    {
        return $this->state($slug, $token, ThreadState::PUBLISH);
    }

    public function hide(string $slug, ?string $token): ?Json
    {
        return $this->state($slug, $token, ThreadState::SECRET);
    }

    public function follow(string $slug, ?string $token): ?Json
    {
        return $this->following($slug, $token, true);
    }

    public function unfollow(string $slug, ?string $token): ?Json
    {
        return $this->following($slug, $token, false);
    }

    public function like(string $slug, ?string $token): ?Json
    {
        if ($denied = $this->denied($token)) {
            return $denied;
        }
        $thread = $this->threads->oneBySlug($slug, false);
        if (null === $thread) {
            return null;
        }
        $user = $this->access->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new Json(['response' => 'Unknown user', 'likes' => \count($thread->getLikes())], 401);
        }
        // The thread's row locked while its likes are looked at: a double click, two tabs, made two Like rows
        // (and Unlike removed one).
        Backends::of($this->unitOfWork, RowLocks::class)->locked($thread, function () use ($thread, $user): void {
            if (!$this->likes->oneByThreadAndUser($thread, $user)) {
                $thread->addLike(new \App\Entity\Thread\Like($user));
                $this->unitOfWork->flush();
            }
        });
        $this->flash('@controllers.thread.like');

        return new Json(['response' => 'OK', 'likes' => \count($thread->getLikes())]);
    }

    public function unlike(string $slug, ?string $token): ?Json
    {
        if ($denied = $this->denied($token)) {
            return $denied;
        }
        $thread = $this->threads->oneBySlug($slug, false);
        if (null === $thread) {
            return null;
        }
        $user = $this->access->getUser();
        if (null === $user) {
            return new Json(['response' => 'Unknown user', 'likes' => \count($thread->getLikes())], 401);
        }
        \assert($user instanceof User);
        $like = $this->likes->oneByThreadAndUser($thread, $user);
        if ($like) {
            $thread->removeLike($like);
        }
        $likes = \count($thread->getLikes());
        $this->unitOfWork->flush();
        $this->flash('@controllers.thread.unlike');

        return new Json(['response' => 'OK', 'likes' => $likes], 201);
    }

    private function state(string $slug, ?string $token, string $state): ?Json
    {
        if ($denied = $this->denied($token)) {
            return $denied;
        }
        $thread = $this->threads->oneBySlug($slug);
        if (!$this->access->isGranted(UserRole::ADMIN)) {
            return new Json(['code' => 401, 'response' => 'Unauthorized']);
        }
        if (null === $thread) {
            return null;
        }
        $thread->setState($state);
        $this->unitOfWork->flush();

        return new Json(['code' => 200, 'response' => 'OK']);
    }

    private function following(string $slug, ?string $token, bool $follow): ?Json
    {
        if ($denied = $this->denied($token)) {
            return $denied;
        }
        $thread = $this->threads->oneBySlug($slug);
        if (!$this->access->isGranted(UserRole::USER)) {
            return new Json(['code' => 401, 'response' => 'Unauthorized']);
        }
        if (null === $thread) {
            return null;
        }
        $user = $this->access->getUser();
        \assert($user instanceof User);
        $follow ? $thread->addFollower($user) : $thread->removeFollower($user);
        $this->unitOfWork->flush();

        return new Json($follow ? ['code' => 200, 'response' => 'OK'] : ['response' => 'OK']);
    }

    private function flash(string $message): void
    {
        $this->requests?->flash('info', (string) ($this->translator?->trans($message) ?? $message));
    }
}
