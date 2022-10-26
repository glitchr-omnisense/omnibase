<?php

namespace Base\Console\Command;

use Base\Console\Command;
use Base\Entity\Thread;
use Base\Enum\ThreadState;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'thread:publishable', aliases:[], description:'')]
class ThreadPublishableCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('publish', null, InputOption::VALUE_NONE, 'Should I publish them ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actionPublish = $input->getOption('publish');

        $threadRepository = $this->entityManager->getRepository(Thread::class);
        $threads = $threadRepository->findByState(ThreadState::FUTURE)->getResult();

        $publishableThreads = array_filter($threads,
        function($thread) use ($actionPublish) {

            if (!$thread->isPublishable()) return false;

            if ($actionPublish)
                $thread->setState(ThreadState::PUBLISH);

            // Refresh database with publishable articles
            $this->entityManager->flush();

            return true;
        });


        // Show future article list
        $nThreads = count($threads);
        $nPublishableThreads = count($publishableThreads);

        if($nThreads) $output->section()->writeln("", OutputInterface::VERBOSITY_VERBOSE);
        foreach ($threads as $key => $thread) {

            $publishableStr = $thread->isPublishable() ? "<warn,bkg>[O]</warn,bkg>" : "[X]";
            $message = $publishableStr." <info>Entry ID #" .($key+1) . "</info>: <ln>". $this->translator->transEntity($thread)." #" . $thread->getId()." \"".$thread->getTitle()."\"</ln>";
            if ( ($parent = $thread->getParent()) )
                $message .= " in <ln>". $this->translator->transEntity($parent)." #" . $parent->getId()." ".$parent->getTitle()." </ln>";

            $message .= " -- Publishable in \"".$thread->getPublishTimeStr()."\"";

            $output->section()->writeln($message, OutputInterface::VERBOSITY_VERBOSE);
        }

        if ($actionPublish && $nPublishableThreads) {
        
            $msg = ' [OK] '.$nThreads.' scheduled thread(s) found: '.$nPublishableThreads.' thread(s) publishable => These are now published';
            $output->writeln('');
            $output->writeln('<info,bkg>'.str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)).'</info,bkg>');
            $output->writeln('');
        
        } else if($nPublishableThreads) {

                $msg = ' [WARN] '.$nThreads.' scheduled thread(s) found: '.$nPublishableThreads.' thread(s) publishable, please confirm using `--publish` option.';
                $output->writeln('');
                $output->writeln('<warning,bkg>'.str_blankspace(strlen($msg)));
                $output->writeln($msg);
                $output->writeln(str_blankspace(strlen($msg)).'</warning,bkg>');
                $output->writeln('');
        } else {

            $msg = ' [OK] '.$nThreads.' scheduled thread(s) found: '.$nPublishableThreads.' thread(s) publishable.';
            $output->writeln('');
            $output->writeln('<info,bkg>'.str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)).'</info,bkg>');
            $output->writeln('');
        }

        return Command::SUCCESS;
        return Command::SUCCESS;
    }
}
