<?php

namespace Base\Field\Behaviour;

/**
 * Kind::LOCALE: a plain select of the locales Intl names (Symfony's
 * LocaleType), its choices given when the field names none.
 */
final class ChoiceList extends AbstractBehaviour
{
    /** @param \Closure(): array<int|string, mixed> $choices */
    public function __construct(private readonly \Closure $choices)
    {
    }

    protected function defaults(): array
    {
        return ['choices' => ($this->choices)(), 'choice_translation_domain' => false];
    }
}
