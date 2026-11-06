<?php

namespace Base\Console\Task;

use App\Entity\User;
use App\Repository\UserRepository;
use Base\Console\Report;
use Base\Database\UnitOfWorks;
use Base\Entity\User\Notification;
use Base\Service\ParameterBagInterface;
use Omnibridge\Persistence\UnitOfWork;

/**
 * user:notifications (plan "omnibase agnostic", step F5e, slice 3): the notifications kept in the database counted
 * (an account's, or everyone's), listed, and the read ones erased when older than base.user.notifications.expiry
 * (or the expiry given), or whatever their age. An unread notification is never erased.
 */
final class UserNotification
{
    public function __construct(private readonly UnitOfWork $unitOfWork, private readonly ParameterBagInterface $parameterBag)
    {
    }

    public function run(UserNotificationInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $notificationRepository = UnitOfWorks::repositories($this->unitOfWork)->of(Notification::class);
        $userRepository = UnitOfWorks::repositories($this->unitOfWork)->of(User::class);
        if (!$userRepository instanceof UserRepository) {
            throw new \LogicException(\sprintf('The repository of %s is not a %s.', User::class, UserRepository::class));
        }

        $notificationExpiry = $input->expiry ?? $this->parameterBag->get('base.user.notifications.expiry');

        // Every account's without an identifier (loadUserByIdentifier() throws on an identifier nobody has).
        $user = null !== $input->user ? $userRepository->loadUserByIdentifier($input->user) : null;
        if ($user) {
            $notifications = $notificationRepository->findByUser($user)->getResult();
            $toErase = $notificationRepository->findByUserAndIsReadAndSentAtOlderThan($user, true, $notificationExpiry)->getResult();
            $isRead = $notificationRepository->findByUserAndIsRead($user, true)->getResult();
        } else {
            $notifications = $notificationRepository->findAll();
            $toErase = $notificationRepository->findByIsReadAndSentAtOlderThan(true, $notificationExpiry)->getResult();
            $isRead = $notificationRepository->findByIsRead(true)->getResult();
        }

        if ($input->clearAll) {
            $toErase = $isRead;
        }

        if ($input->show) {
            foreach ($notifications as $key => $notification) {
                $message = '<info>Entry ID #'.($key + 1).'</info> / <red>User "'.$notification->getUser().'"</red> / <ln>Notifications #'.$notification->getId().'</ln> : '.str_shorten($notification->getContent(), 50);
                if (\in_array($notification, $toErase)) {
                    $message .= '<warning><<-- READY TO ERASE</warning>';
                }
                $report->line($message);
            }
        }
        $report->line('<info>'.\count($notifications).' notification(s)</info> found, <warning>'.\count($isRead).' notification(s)</warning> are marked as read, <red>'.\count($toErase).' notification(s)</red> older than '.$notificationExpiry);

        if (($input->clear || $input->clearAll) && $toErase) {
            $report->line('<warning>These notifications are now erased..</warning>');

            // The expired read ones only, and one flush for all.
            foreach ($toErase as $notification) {
                $this->unitOfWork->remove($notification);
            }
            $this->unitOfWork->flush();
        }

        return $report;
    }
}
