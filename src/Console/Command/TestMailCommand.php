<?php

namespace App\Command;

use App\Repository\UserRepository;
use Base\Notifier\NotifierInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notifier:mail:test',
    description: 'Send a test email to either a specific user or to the technical recipient'
)]
class NotifierTestCommand extends Command
{
    protected NotifierInterface $notifier;
    protected UserRepository $userRepository;

    public function __construct(NotifierInterface $notifier, UserRepository $userRepository)
    {
        parent::__construct();
        $this->notifier = $notifier;
        $this->userRepository = $userRepository;
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

        $userId = $input->getOption('user');

        if ($userId) {
            $user = $this->userRepository->find($userId);

            if (!$user) {
                $io->error("User with ID $userId not found.");
                return Command::FAILURE;
            }

            $io->note("Sending test email to user ID: $userId");

            $this->notifier->testEmail($user);

            $io->success("Test email sent to user ID: $userId");
        } else {
            $io->error("No user ID provided. Please specify a user ID using the --user option.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

}