<?php

namespace Base\Console\Task;

use Base\Console\Report;

/**
 * cache:clear:sessions (plan "omnibase agnostic", step F5e, slice 3): every session file of the host's folder
 * removed, so that every visitor is signed out.
 */
final class CacheClearSessions
{
    public function run(CacheClearSessionsInput $input, ?\Closure $sink = null): Report
    {
        if (is_dir($input->directory)) {
            foreach (glob($input->directory.'/'.$input->pattern) ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        return (new Report($sink))->write('<info> [INFO] Session files cleared from '.$input->directory.'.</info>'.PHP_EOL, true);
    }
}
