<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Notifier\Abstract\BaseNotifier;

/**
 * app:notifier (plan "omnibase agnostic", step F5e, slice 3): the templated notifications the application's
 * notifier offers - its methods and its parents', BaseNotifier's own left out.
 */
final class NotifierOverview
{
    public function run(NotifierOverviewInput $input = new NotifierOverviewInput(), ?\Closure $sink = null): Report
    {
        $report = (new Report($sink))->line('Available templated notifications:');
        foreach ((new \ReflectionClass($input->notifier))->getMethods() as $method) {
            if (BaseNotifier::class !== $method->class) {
                $report->line(' * <info>'.$method->name.'</info>');
            }
        }

        return $report;
    }
}
