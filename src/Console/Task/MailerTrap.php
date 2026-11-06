<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Mailer\MailTrap;

/**
 * base:mailer:trap (plan "omnibase agnostic", step F5e, slice 3): outgoing mail held during an incident (the trap
 * sits at send time, so mail already queued is caught too), what was held looked at, the wrong ones thrown away and
 * the rest let go - released through the host's transport ($send), around the trap. Held mail outlives the trap on
 * purpose: disarming restores delivery without deciding, silently, what happens to what was caught.
 */
final class MailerTrap
{
    public function __construct(private readonly MailTrap $trap)
    {
    }

    /** @param ?\Closure(\Symfony\Component\Mime\RawMessage, \Symfony\Component\Mailer\Envelope): mixed $send the host's transport (not its mailer, which would hand a release back to an armed trap) */
    public function run(MailerTrapInput $input, ?\Closure $sink = null, ?\Closure $send = null): Report
    {
        $report = new Report($sink);

        return match ($input->action) {
            'status' => $this->status($report),
            'on', 'arm' => $this->arm($report, $input->reason),
            'off', 'disarm' => $this->disarm($report),
            'list' => $this->list($report),
            'show' => $this->show($report, $input->id),
            'purge' => $this->purge($report, $input->id, $input->all),
            'release' => $this->release($report, $input->id, $input->all, $send),
            default => $this->unknown($report, $input->action),
        };
    }

    private function unknown(Report $report, string $action): Report
    {
        $report->error(\sprintf('Unknown action "%s". Try: status, on, off, list, show, purge, release.', $action));

        return $report->fail();
    }

    private function status(Report $report): Report
    {
        $held = \count($this->trap->all());
        if ($details = $this->trap->armedDetails()) {
            $report->warning(\sprintf('Trap ARMED since %s%s - no mail is leaving this application.', $details['since'], $details['reason'] ? ' ('.$details['reason'].')' : ''));
        } else {
            $report->success('Trap off - mail is being delivered normally.');
        }

        $report->line(\sprintf(' Held messages: <info>%d</info>', $held));
        $report->line(\sprintf(' Directory:     %s', $this->trap->getDirectory()));
        if (0 !== $held && !$this->trap->isArmed()) {
            $report->notice('Messages are still held from an earlier session. Release or purge them - the trap being off does not send them.');
        }

        return $report;
    }

    private function arm(Report $report, ?string $reason): Report
    {
        $this->trap->arm($reason);
        $report->warning('Trap ARMED. Nothing will be delivered until "base:mailer:trap off".');

        return $report->line(' Already-queued mail is caught too: the trap sits at send time, not at queue time.');
    }

    private function disarm(Report $report): Report
    {
        $this->trap->disarm();
        $report->success('Trap off - mail is being delivered again.');
        if ($held = \count($this->trap->all())) {
            $report->notice(\sprintf('%d message(s) are still held. They are NOT sent by disarming - use "release" or "purge".', $held));
        }

        return $report;
    }

    private function list(Report $report): Report
    {
        $rows = $this->trap->all();
        if ([] === $rows) {
            return $report->success('Nothing held.');
        }

        return $report->table(['id', 'captured', 'to', 'subject'], array_map(static fn (array $r) => [
            (string) ($r['id'] ?? '?'),
            (string) ($r['capturedAt'] ?? '?'),
            implode(', ', $r['to'] ?? []),
            (string) ($r['subject'] ?? '(no subject)'),
        ], $rows));
    }

    private function show(Report $report, ?string $id): Report
    {
        if (null === $id) {
            $report->error('Which one? Pass an id from "base:mailer:trap list".');

            return $report->fail();
        }
        $held = $this->trap->read($id);
        if (null === $held) {
            $report->error(\sprintf('No held message "%s".', $id));

            return $report->fail();
        }

        return $report->line($held[0]->toString());
    }

    private function purge(Report $report, ?string $id, bool $all): Report
    {
        $targets = $this->targets($report, $id, $all);
        if (null === $targets) {
            return $report->fail();
        }

        $done = 0;
        foreach ($targets as $target) {
            $done += $this->trap->forget($target) ? 1 : 0;
        }

        return $report->success(\sprintf('%d message(s) discarded. They were never sent.', $done));
    }

    private function release(Report $report, ?string $id, bool $all, ?\Closure $send): Report
    {
        $targets = $this->targets($report, $id, $all);
        if (null === $targets) {
            return $report->fail();
        }
        if (null === $send && [] !== $targets) {
            throw new \LogicException('base:mailer:trap release sends through the host\'s mail transport, which was not given.');
        }

        if ($this->trap->isArmed()) {
            // Sending on the transport does not skip the trap (it dispatches the message event too): bypass() does.
            $report->warning('The trap is still armed - these are being sent anyway, deliberately.');
        }

        $sent = 0;
        foreach ($targets as $target) {
            $held = $this->trap->read($target);
            if (null === $held) {
                $report->error(\sprintf('No held message "%s".', $target));

                continue;
            }

            [$message, $envelope] = $held;
            $this->trap->bypass(static fn () => $send($message, $envelope));
            $this->trap->forget($target);
            ++$sent;
        }

        return $report->success(\sprintf('%d message(s) sent.', $sent));
    }

    /** @return ?list<string> the messages aimed at, null when none was named */
    private function targets(Report $report, ?string $id, bool $all): ?array
    {
        if ($all) {
            return array_map(static fn (array $r) => (string) ($r['id'] ?? ''), $this->trap->all());
        }
        if (null === $id) {
            $report->error('Pass an id from "base:mailer:trap list", or --all.');

            return null;
        }

        return [$id];
    }
}
