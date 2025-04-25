<?php

namespace Base\Console\Command;

use Base\Console\Command;
use App\Repository\UserRepository;
use Base\Notifier\NotifierInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notifier:mail:test',
    description: 'Send a test email to either a specific user or to the technical recipient'
)]
class NotifierMailTestCommand extends Command
{
    protected NotifierInterface $notifier;
    protected UserRepository $userRepository;

    public function __construct(...$args)
    {
        $this->notifier = first(extract_instanceof(NotifierInterface::class, ...$args));
        $this->userRepository = first(extract_instanceof(UserRepository::class, ...$args));
        parent::__construct(...$args);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'user',
                null,
                InputOption::VALUE_OPTIONAL,
                'User ID (or email, or username) to send the notification to'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->notifier->hasLoopback() && $this->notifier->getTechnicalRecipient()) {
            $io->note('Notifier loopback is enabled. Notifications will be sent to the technical recipient: '. PHP_EOL . $this->notifier->getTechnicalRecipient());
        }

        $userId = $input->getOption('user');        
        $user = $this->userRepository->findByIdOrEmailOrUsername($userId)->getResult();
        if ($userId && !$user) {
            $io->warning("No user found by ID, email or username using `$userId`.");
        }

        if(!$user) {
            $user = $this->userRepository->findByEmail($this->notifier->getTechnicalRecipient()->getEmail())->getResult();
            if (!$user) {
                $io->warning("No user found in database for the technical recipient: " . $this->notifier->getTechnicalRecipient());
            }
        }

        if(!$user) {
            
            $user = $this->userRepository->findOne()->getResult();
            if (!$user) {
                $io->warning("No user found in database for the technical recipient: " . $this->notifier->getTechnicalRecipient());
                return Command::FAILURE;
            }
        }
        
        $io->note("Sending test email to user ID: $userId");
        $this->notifier->testEmail($user);

        $io->success("Test email sent to user ID: $userId");
        return Command::SUCCESS;
    }
}