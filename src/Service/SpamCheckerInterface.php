<?php

namespace Base\Service;

use Base\Enum\SpamApi;
use Base\Service\Model\SpamProtectionInterface;

interface SpamCheckerInterface
{
    public function check(SpamProtectionInterface $candidate, array $context = [], $api = SpamApi::AKISMET): int;
    public function score(SpamProtectionInterface $candidate, array $context = [], $api = SpamApi::AKISMET): int;
}
