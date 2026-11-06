<?php

namespace Base\Console\Task;

use Base\Service\TranslatorInterface;

/** translation:enums' options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class TranslationEnumsInput
{
    /** @param string $source the application's App\ folder (Symfony: ./src) */
    public function __construct(
        public string $source = 'src',
        public string $enum = '',
        public string $suffix = TranslatorInterface::NOUN_SINGULAR,
        public ?string $locale = null,
    ) {
    }
}
