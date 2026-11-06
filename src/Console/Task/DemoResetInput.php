<?php

namespace Base\Console\Task;

/** demo:reset's options and the base.demo.reset.* parameters (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class DemoResetInput
{
    /**
     * @param list<string> $purge  folders emptied before the fixtures are loaded (base.demo.reset.purge)
     * @param bool         $orphans the uploaded files no row names deleted (base.demo.reset.orphans)
     * @param list<string> $groups the fixture groups loaded (base.demo.reset.groups); none: every fixture
     */
    public function __construct(
        public bool $keepFiles = false,
        public array $purge = [],
        public bool $orphans = true,
        public array $groups = [],
    ) {
    }
}
