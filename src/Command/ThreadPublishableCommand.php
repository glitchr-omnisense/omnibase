<?php

namespace Base\Command;

use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Repository\ThreadRepository;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class ThreadPublishableCommand extends Command
{
    protected static $defaultName = 'thread:publishable';

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('publish', null, InputOption::VALUE_NONE, 'Should I publish them ?');
        $this->addOption('show',    null, InputOption::VALUE_NONE, 'Should I show you the publishable ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface)
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');

        $actionPublish = $input->getOption('publish');
        $actionShow    = $input->getOption('show');

        $threadRepository = $this->entityManager->getRepository(Thread::class);
        $threads = $threadRepository->findByState(ThreadState::FUTURE)->getResult();

        $publishableThreads = array_filter($threads,
        function($thread) use ($actionPublish) {

            if (!$thread->isPublishable()) return false;

            if ($actionPublish)
                $thread->setState(ThreadState::PUBLISHED);

            return true;
        });

        // Refresh database with publishable articles
        $this->entityManager->flush();

        // Show future article list
        $nThreads = count($threads);
        $nPublishableThreads = count($publishableThreads);

        if($actionPublish || $actionShow) {

            foreach ($publishableThreads as $key => $thread) {

                $message = "Entry ID #" .($key+1) . " / Thread[". get_class($thread) . "] #" . $thread->getId();
                if ( ($parent = $thread->getParent()) )
                    $message .= " / Parent[". get_class($parent)."] #" . $parent->getId();

                $message .= " -- Title: \"".$thread->getTitle()."\"";

                $output->section()->writeln($message);
            }
        }

        $output->section()->writeln($nThreads . ' scheduled thread(s) found => ' . $nPublishableThreads . ' thread(s) publishable.');
        if ($actionPublish && $nPublishableThreads) $output->section()->writeln('=> Threads now published.');

        return Command::SUCCESS;
    }
}
