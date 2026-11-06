<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\RowLocks;
use Base\Enum\ThreadState;
use Base\Repository\Threads;
use Base\Service\TranslatorInterface;
use Omnibridge\Persistence\UnitOfWork;

/**
 * thread:publishable (plan "omnibase agnostic", step F5e, slice 3): the scheduled threads (ThreadState::FUTURE),
 * those whose time has come, and - asked - those published, each under its row's lock and read again, so that two
 * runs at once (the cron, an administrator's edit) publish it once.
 */
final class ThreadPublishable
{
    /** @param Threads<\Base\Entity\Thread> $threads */
    public function __construct(private readonly Threads $threads, private readonly UnitOfWork $unitOfWork, private readonly TranslatorInterface $translator)
    {
    }

    public function run(ThreadPublishableInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $threads = $this->threads->byStateUncached(ThreadState::FUTURE);

        $publishableThreads = array_filter($threads, function ($thread) use ($input): bool {
            if (!$thread->isPublishable()) {
                return false;
            }

            if ($input->publish) {
                // Its row locked and read again: two runs at once both published it, and its authors and mentioned
                // members were mailed twice.
                Backends::of($this->unitOfWork, RowLocks::class)->locked($thread, function () use ($thread): void {
                    $this->unitOfWork->refresh($thread);
                    if ($thread->isPublishable()) {
                        $thread->poke();
                        $this->unitOfWork->flush();
                    }
                });
            }

            return true;
        });

        $nThreads = \count($threads);
        $nPublishableThreads = \count($publishableThreads);

        if ($nThreads) {
            $report->line('', Report::VERBOSE);
        }
        foreach ($threads as $key => $thread) {
            $publishableStr = $thread->isPublishable() ? '<info,bkg>[O]</info,bkg>' : '[X]';
            $message = $publishableStr.' <info>Entry ID #'.($key + 1).'</info>: <ln>'.$this->translator->transEntity($thread).' #'.$thread->getId().' "'.$thread->getTitle().'"</ln>';
            if ($parent = $thread->getParent()) {
                $message .= ' in <ln>'.$this->translator->transEntity($parent).' #'.$parent->getId().' '.$parent->getTitle().' </ln>';
            }

            $report->line($message.' -- Publishable in "'.$thread->getPublishTimeStr().'"', Report::VERBOSE);
        }

        if ($input->publish && $nPublishableThreads) {
            self::banner($report, 'info', ' [OK] '.$nThreads.' scheduled thread(s) found: '.$nPublishableThreads.' thread(s) publishable => These are now published. ');
        } elseif ($nPublishableThreads) {
            self::banner($report, 'warning', ' [WARN] '.$nThreads.' scheduled thread(s) found: '.$nPublishableThreads.' thread(s) publishable, please confirm using `--publish` option. ');
        } else {
            self::banner($report, 'info', ' [OK] '.$nThreads.' scheduled thread(s) found: '.$nPublishableThreads.' thread(s) publishable. ');
        }

        return $report;
    }

    /** omnibase's banner: the message between two blank lines of its width, on the style's background. */
    public static function banner(Report $report, string $style, string $message, bool $around = true): Report
    {
        if ($around) {
            $report->line('');
        }
        $report->line('<'.$style.',bkg>'.str_blankspace(\strlen($message)));
        $report->line($message);
        $report->line(str_blankspace(\strlen($message)).'</'.$style.',bkg>');

        return $around ? $report->line('') : $report;
    }
}
