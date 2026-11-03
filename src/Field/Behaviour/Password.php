<?php

namespace Base\Field\Behaviour;

/**
 * Kind::PASSWORD: omnibase's password, typed twice by default (`plain`, then
 * `plain_repeater`, as PasswordType names its two inputs), its strength
 * shown (hint, the strength bounds) and checked; the value is what was typed,
 * untrimmed, refused when the two inputs differ.
 */
final class Password extends AbstractBehaviour
{
    protected function defaults(): array
    {
        return [
            'inline' => true, 'secure' => true, 'hint' => true, 'revealer' => false, 'repeater' => true,
            'autocomplete' => false, 'suggestions' => false,
            // Base\Validator\Constraints\Password's fallbacks, written out: that constraint is symfony/validator's.
            'min_length' => 8, 'min_strength' => 0, 'max_strength' => 5,
            'required' => false, 'options' => [], 'options[repeater]' => [], 'invalid_message' => '@fields.password.invalid_message',
        ];
    }

    public function options(array $given): array
    {
        $options = parent::options($given);
        $options['revealer'] = $options['revealer'] || !$options['repeater'];

        return $options;
    }

    public function vars(array $options, mixed $shown, string $id): array
    {
        return array_intersect_key($options, array_flip(['inline', 'hint', 'secure', 'repeater', 'revealer', 'min_length', 'min_strength', 'max_strength', 'autocomplete', 'suggestions', 'required']));
    }

    protected function controller(): ?string
    {
        return 'password2';
    }

    /** The typed password, from its two inputs; refused when they differ. */
    public function reverse(mixed $submitted, array $options): mixed
    {
        if (!\is_array($submitted)) {
            return $submitted;
        }
        $plain = $submitted['plain'] ?? null;
        if ($options['repeater'] && ($plain ?? '') !== ($submitted['plain_repeater'] ?? '')) {
            throw new \UnexpectedValueException('The two passwords differ.');
        }

        return $plain;
    }

    /** A password is never shown. */
    public function transform(mixed $data, array $options): mixed
    {
        return null;
    }
}
