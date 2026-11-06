<?php

namespace Base\Console\Task;

/** translation:crud's options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class TranslationCrudInput
{
    /** @param string $source the application's App\ folder (Symfony: ./src) */
    public function __construct(
        public string $source = 'src',
        public string $crud = '',
        public string $action = 'index',
        public string $suffix = 'title',
        public ?string $locale = null,
    ) {
    }
}
