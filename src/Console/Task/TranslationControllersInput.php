<?php

namespace Base\Console\Task;

/** translation:controllers' options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class TranslationControllersInput
{
    /** @param array<string, string> $routes the host's routes: name => controller ("App\...::action") */
    public function __construct(
        public array $routes = [],
        public string $controller = '',
        public string $suffix = 'title',
        public ?string $locale = null,
    ) {
    }
}
