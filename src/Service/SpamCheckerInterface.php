<?php

namespace Base\Service;

use Base\Enum\SpamApi;
use Base\Service\Model\SpamProtectionInterface;

/**
 *
 */
interface SpamCheckerInterface
{
    /**
     * @param SpamProtectionInterface $candidate
     * @param array $context
     * @param $api
     * @return int
     */
    public function check(SpamProtectionInterface $candidate, array $context = [], $api = SpamApi::AKISMET): int;

    /**
     * @param SpamProtectionInterface $candidate
     * @param array $context
     * @param $api
     * @return int
     */
    public function score(SpamProtectionInterface $candidate, array $context = [], $api = SpamApi::AKISMET): int;
}
