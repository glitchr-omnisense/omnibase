<?php

namespace Base\Repository\User;

use App\Entity\User;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\User\Notification;
use Base\Repository\User\Notifications;
use Base\Database\Repository\Finder\Group;

/**
 * @method Notification|null find($id)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository implements Notifications
{
    /** The unread ones with something to show, counted by the database: it runs on every page for the toolbar badge. */
    public function countUnreadFor(User $user): int
    {
        return $this->count($this->visibleTo($user) + ['isRead' => false]);
    }

    /**
     * The user's notifications that have something to show, newest first.
     *
     * @return Notification[]
     */
    public function findVisibleFor(User $user, int $limit = 100, int $offset = 0, bool $unreadOnly = false): array
    {
        return $this->findBy($this->visibleTo($user) + ($unreadOnly ? ['isRead' => false] : []), ['sentAt' => 'DESC', 'id' => 'DESC'], $limit, $offset);
    }

    /**
     * The criteria of the rows with a title, a subject or a content (a finder group, NotEmpty being
     * "neither null nor ''"). An e-mail-only notification (a templated mail sent through setUser()) is
     * kept with all three empty and has nothing a list or a toast could show: neither counted nor listed.
     *
     * @return array<int|string, mixed>
     */
    private function visibleTo(User $user): array
    {
        return ['user' => $user, Group::by('TitleNotEmptyOrSubjectNotEmptyOrContentNotEmpty')];
    }
}
