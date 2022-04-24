<?php

namespace Base\Console\Command;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Service\BaseService;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Base\Console\Command;
use Doctrine\ORM\EntityManagerInterface;

class UserNotificationCommand extends Command
{
    protected static $defaultName = 'user:notifications';

    public function __construct(EntityManagerInterface $entityManager, BaseService $baseService)
    {
        $this->entityManager = $entityManager;
        $this->baseService = $baseService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('user',  null, InputOption::VALUE_REQUIRED, 'Should I consider them with a specific user ?');
        $this->addOption('impersonator',  null, InputOption::VALUE_OPTIONAL, 'Should I consider them with a specific impersonator user ?');
        $this->addOption('event',  null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific event ?');
        $this->addOption('expiry',  null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific expiry value ?');
        $this->addOption('pretty',  null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific pretty value ?');
        $this->addOption('statutCode',  null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific status code ?');
        
        $this->addOption('show', null, InputOption::VALUE_NONE, 'Should I show them ?');
        $this->addOption('clear', null, InputOption::VALUE_NONE, 'Should I clear them ?');
        $this->addOption('clear-all', null, InputOption::VALUE_NONE, 'Should I clear them all?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userIdentifier = $input->getOption('user');
        $actionClear    = $input->getOption('clear');
        $actionShow     = $input->getOption('show');

        $notificationRepository = $this->entityManager->getRepository(Notification::class);
        $userRepository = $this->entityManager->getRepository(User::class);

        $notificationExpiry = $this->baseService->getParameterBag("base.user.notifications.expiry");

        // Format monitored entries
        $user               = $userRepository->loadUserByIdentifier($userIdentifier);
        if($user) {

            $notifications  = $notificationRepository->findByUser($user)->getResult();
            $notifications_toErase  = $notificationRepository->findByUserAndIsReadAndSentAtOlderThan($user, true, $notificationExpiry, [], "user.id")->getResult();
            $notifications_isRead  = $notificationRepository->findByUserAndByIsRead($user, true, [], "user.id")->getResult();
        
        } else {

            $notifications  = $notificationRepository->findAll();
            $notifications_toErase  = $notificationRepository->findByIsReadAndSentAtOlderThan(true, $notificationExpiry, [], "user.id")->getResult();
            $notifications_isRead  = $notificationRepository->findByIsRead(true, [], "user.id")->getResult();
        }

        // Show notification list
        $nbNotifications = count($notifications);
        $nbNotifications_toErase = count($notifications_toErase);
        $nbNotifications_isRead = count($notifications_isRead);

        if($actionShow) {

            foreach ($notifications as $key => $notification) {

                $message = "";
                $message .= "<info>Entry ID #" .($key+1) . "</info> / <red>User \"".$notification->getUser()."\"</red> / <ln>Notifications #" . $notification->getId()."</ln> : ". str_shorten($notification->getContent(), 50);

                if(in_array($notification, $notifications_toErase)) $message .= "<warning><<-- READY TO ERASE</warning>";
                $output->section()->writeln($message);
            }
        }
        $output->section()->writeln("<info>".$nbNotifications . " notification(s)</info> found, <warning>".$nbNotifications_isRead." notification(s)</warning> are marked as read, <red>".$nbNotifications_toErase. " notification(s)</red> older than ".$notificationExpiry);

        if($actionClear && $nbNotifications_toErase) {

            $output->section()->writeln('<warning>These notifications are now erased..</warning>');

            foreach($notifications as $notification) {
                $this->entityManager->remove($notification);
                $this->entityManager->flush($notification);
            }
        }

        return Command::SUCCESS;
    }
}
