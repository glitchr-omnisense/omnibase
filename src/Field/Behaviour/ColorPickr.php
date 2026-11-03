<?php

namespace Base\Field\Behaviour;

/**
 * Kind::COLOR_PICKER: a colour picked in Pickr (its settings in `pickr`, the
 * swatches and the buttons' words included), shown as #RRGGBBAA: a short
 * colour completed with an opaque alpha, anything else as the transparent
 * black, which is no colour when the field may be empty (`is_nullable`).
 */
final class ColorPickr extends AbstractBehaviour
{
    public const NONE = '#00000000';

    public const SWATCHES = ['#001F3F', '#0074d9', '#7fdbff', '#39cccc', '#3d9970', '#2ecc40', '#01ff70', '#ffdc00', '#ff851b', '#ff4136', '#85144B', '#f012be', '#b10dc9', '#111111', '#AAAAAA'];

    protected function defaults(): array
    {
        return [
            'pickr' => [
                'el' => null,
                'container' => 'body',
                'theme' => 'classic',
                'closeOnScroll' => false,
                'appClass' => 'custom-class',
                'useAsButton' => true,
                'inline' => false,
                'autoReposition' => true,
                'sliders' => 'v',
                'disabled' => false,
                'lockOpacity' => false,
                'outputPrecision' => 0,
                'comparison' => true,
                'default' => '#42445a',
                'swatches' => self::SWATCHES,
                'defaultRepresentation' => 'HEX',
                'showAlways' => false,
                'closeWithKey' => 'Escape',
                'position' => 'bottom-middle',
                'adjustableNumbers' => true,
                'components' => [
                    'palette' => true,
                    'preview' => true,
                    'opacity' => true,
                    'hue' => true,
                    'interaction' => ['hex' => true, 'rgba' => true, 'hsla' => true, 'hsva' => false, 'cmyk' => false, 'input' => true, 'cancel' => false, 'clear' => false, 'save' => true],
                ],
                'strings' => [
                    'save' => self::trans('@fields.color.buttons.save'),
                    'clear' => self::trans('@fields.color.buttons.clear'),
                    'cancel' => self::trans('@fields.color.buttons.cancel'),
                ],
            ],
            'webpack_entry' => 'form.color',
            'is_nullable' => true,
        ];
    }

    public function vars(array $options, mixed $shown, string $id): array
    {
        $pickr = $options['pickr'];
        $pickr['el'] = '#'.$id;

        return ['type' => 'text', 'attr' => ['data-color-pickr' => json_encode($pickr), 'class' => 'form-color']];
    }

    public function transform(mixed $data, array $options): mixed
    {
        $value = \is_string($data) ? $data : '';

        return match (\strlen($value)) {
            4 => $value.'F',
            7 => $value.'FF',
            9 => $value,
            default => self::NONE,
        };
    }

    public function reverse(mixed $submitted, array $options): mixed
    {
        return self::NONE == $submitted && $options['is_nullable'] ? null : $submitted;
    }

    protected function controller(): ?string
    {
        return 'color';
    }
}
