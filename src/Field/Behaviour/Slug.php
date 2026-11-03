<?php

namespace Base\Field\Behaviour;

/**
 * Kind::SLUG: an address's word, made from another field as it is typed (its
 * `target`, a path in the form: "translations.title" is the default
 * language's title) and locked once written by hand. Its settings are the
 * slug script's: the separator, the characters kept, upper case, whether
 * anything else is dropped (strict, by default when nothing is kept), the
 * lock.
 */
final class Slug extends AbstractBehaviour
{
    protected function defaults(): array
    {
        return ['separator' => '-', 'keep' => null, 'upper' => false, 'lock' => null, 'strict' => null, 'target' => null, 'required' => false];
    }

    public function options(array $given): array
    {
        $options = parent::options($given);
        $options['strict'] ??= empty($options['keep'] ?? '');

        return $options;
    }

    /**
     * keep, separator, upper, strict, lock as the slug script reads them;
     * `target` the path of the field it is made from, as a list (null: the
     * form's own), its id resolved by the renderer in the whole form.
     */
    public function vars(array $options, mixed $shown, string $id): array
    {
        return [
            'keep' => $options['keep'] ? preg_quote((string) $options['keep']) : null,
            'separator' => $options['separator'] ?: null,
            'upper' => json_encode($options['upper']),
            'strict' => json_encode($options['strict']),
            'lock' => json_encode($options['lock']),
            'target' => \is_string($options['target']) && '' !== $options['target'] ? explode('.', ltrim($options['target'], '.')) : null,
        ];
    }

    protected function controller(): ?string
    {
        return 'slug';
    }
}
