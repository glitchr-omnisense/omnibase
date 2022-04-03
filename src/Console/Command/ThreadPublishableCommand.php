<?php

namespace Base\Console\Command;

use Base\Console\Command;
use Base\Entity\Thread;
use Base\Enum\ThreadState;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ThreadPublishableCommand extends Command
{
    protected static $defaultName = 'thread:publishable';

    public function __construct(EntityManagerInterface $entityManager)
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
        $actionPublish = $input->getOption('publish');
        $actionShow    = $input->getOption('show');

        $threadRepository = $this->entityManager->getRepository(Thread::class);
        $threads = $threadRepository->findByState(ThreadState::FUTURE)->getResult();

        $publishableThreads = array_filter($threads,
        function($thread) use ($actionPublish) {

            if (!$thread->isPublishable()) return false;

            if ($actionPublish)
                $thread->setState(ThreadState::PUBLISH);

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
