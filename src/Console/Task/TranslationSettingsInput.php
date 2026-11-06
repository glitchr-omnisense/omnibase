<?php

namespace Base\Console\Task;

/** translation:settings' options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class TranslationSettingsInput
{
    public function __construct(
        public ?string $path = null,
        public bool $raw = false,
        public ?string $locale = null,
    ) {
    }
}
