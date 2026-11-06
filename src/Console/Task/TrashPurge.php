<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\TrashManager;

/**
 * trash:purge (plan "omnibase agnostic", step F5e, slice 3): the trash emptied of everything past its grace period
 * (base.extension.empty_trash, "+7 days" by default). A dry run unless forced: nothing else in the stack destroys
 * content beyond recovery.
 */
final class TrashPurge
{
    public function __construct(private readonly TrashManager $trashManager)
    {
    }

    public function run(TrashPurgeInput $input, ?\Closure $sink = null, ?\DateTimeInterface $now = null): Report
    {
        $report = new Report($sink);
        $now ??= new \DateTime();
        $expired = $this->trashManager->expired($now, $input->limit);

        if (empty($expired)) {
            $count = $this->trashManager->count();

            return $report->line('<info>Nothing to purge.</info> The trash holds '.$count.' entr'.(1 === $count ? 'y' : 'ies').', none past its grace period.');
        }

        foreach ($expired as $trashBall) {
            $label = $trashBall->getEntityData()['label'] ?? null;
            $report->line(\sprintf(
                '  %s <info>%s</info> #%s%s <ln>(expired %s)</ln>',
                $input->force ? 'destroying' : 'would destroy',
                $trashBall->getEntityClass(),
                $trashBall->getEntityId(),
                $label ? ' "'.$label.'"' : '',
                $trashBall->getPermanentAfter()?->format('Y-m-d H:i') ?? '?'
            ));
        }

        if (!$input->force) {
            $report->line('');

            return $report->line('<warning>Dry run.</warning> '.\count($expired).' entr'.(1 === \count($expired) ? 'y' : 'ies').' would be destroyed. Pass --force to do it.');
        }

        $destroyed = $this->trashManager->purge($now, $input->limit);
        $report->line('');

        return $report->line('<info>Purged.</info> '.$destroyed.' entit'.(1 === $destroyed ? 'y' : 'ies').' destroyed.');
    }
}
