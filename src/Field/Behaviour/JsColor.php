<?php

namespace Base\Field\Behaviour;

/**
 * Kind::COLOR: a colour typed or picked in jscolor, shown as #RRGGBBAA (an
 * empty one as #00000000), kept as short as it can be written (#F00); the
 * transparent black is no colour when the field may be empty (`is_nullable`).
 */
final class JsColor extends AbstractBehaviour
{
    public const NONE = '#00000000';

    protected function defaults(): array
    {
        return ['jscolor' => [], 'is_nullable' => true, 'webpack_entry' => 'form.color'];
    }

    public function vars(array $options, mixed $shown, string $id): array
    {
        // A text input, as Symfony's TextType draws it: jscolor is the picker, not the browser's.
        return ['type' => 'text', 'attr' => ['data-jscolor' => json_encode($options['jscolor']), 'class' => 'form-color']];
    }

    public function transform(mixed $data, array $options): mixed
    {
        return expandhex(\is_string($data) ? $data : null, true) ?? self::NONE;
    }

    public function reverse(mixed $submitted, array $options): mixed
    {
        if (!\is_string($submitted)) {
            return $submitted;
        }
        $color = expandhex($submitted, true);
        if (self::NONE == $color && $options['is_nullable']) {
            return null;
        }

        return shrinkhex($color);
    }

    protected function controller(): ?string
    {
        return 'jscolor';
    }
}
