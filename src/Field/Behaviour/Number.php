<?php

namespace Base\Field\Behaviour;

/**
 * Kind::NUMBER: a number shown in the page's language (48,857 in French, as
 * Symfony's NumberType writes it: its scale, else three decimals at most;
 * grouping off by default), divided by `divisor` when shown and multiplied
 * back when read, its prefix and suffix stripped; the steppers' settings
 * (step, throttle, min, max, the keys) for the number script.
 */
final class Number extends AbstractBehaviour
{
    protected function defaults(): array
    {
        return [
            'scale' => null, 'grouping' => false, 'rounding_mode' => \NumberFormatter::ROUND_HALFUP, 'html5' => false, 'input' => 'number',
            'stepUp' => null, 'stepDown' => null, 'divisor' => 1, 'step' => 1, 'throttleUp' => null, 'throttleDown' => null,
            'inline' => false, 'throttle' => 50, 'min' => null, 'max' => null, 'suffix' => null, 'prefix' => null,
            'autocomplete' => false, 'keyUp' => true, 'keyDown' => true, 'disabled' => false,
        ];
    }

    public function vars(array $options, mixed $shown, string $id): array
    {
        return [
            'stepUp' => $options['stepUp'] ?? $options['step'],
            'stepDown' => $options['stepDown'] ?? $options['step'],
            'keyUp' => $options['keyUp'],
            'keyDown' => $options['keyDown'],
            'throttleUp' => $options['throttleUp'] ?? $options['throttle'],
            'throttleDown' => $options['throttleDown'] ?? $options['throttle'],
            'min' => $options['min'],
            'divisor' => $options['divisor'],
            'max' => $options['max'],
            'suffix' => $options['suffix'],
            'inline' => $options['inline'],
            'prefix' => $options['prefix'],
            'autocomplete' => $options['autocomplete'],
        ];
    }

    protected function controller(): ?string
    {
        return 'number';
    }

    public function transform(mixed $data, array $options): mixed
    {
        if (null === $data || '' === $data) {
            return '';
        }
        if (!is_numeric($data)) {
            return $data;
        }
        $value = $data + 0;
        if (1 != $options['divisor']) {
            $value /= $options['divisor'];
        }
        $formatter = $this->formatter($options);
        $shown = $formatter->format($value);

        // Symfony's own: a non-breaking space where the locale groups by one.
        return str_replace("\u{00A0}", ' ', (string) $shown);
    }

    /** What is typed, its prefix and suffix stripped, read in the page's language; the number for the binder. */
    public function reverse(mixed $submitted, array $options): mixed
    {
        if (!\is_string($submitted) || '' === trim($submitted)) {
            return $submitted;
        }
        $value = $submitted;
        if ($options['prefix']) {
            $value = str_lstrip($value, (string) $options['prefix']);
        }
        if ($options['suffix']) {
            $value = str_rstrip($value, (string) $options['suffix']);
        }
        $value = str_replace(["\u{00A0}", "\u{202F}"], ' ', trim($value));
        $formatter = $this->formatter($options);
        $position = 0;
        $number = $formatter->parse(str_replace(' ', "\u{00A0}", $value), \NumberFormatter::TYPE_DOUBLE, $position);
        if (false === $number || $position < \strlen(str_replace(' ', "\u{00A0}", $value))) {
            // The page's language aside, the dot of a script or a paste.
            $plain = str_replace([' ', ','], ['', '.'], $value);
            if (!is_numeric($plain)) {
                throw new \UnexpectedValueException(sprintf('"%s" is not a number.', $submitted));
            }
            $number = (float) $plain;
        }
        if (null !== $options['scale']) {
            $number = round($number, (int) $options['scale']);
        }
        if (1 != $options['divisor']) {
            $number *= $options['divisor'];
        }

        return 'string' === $options['input'] ? (string) $number : $number;
    }

    /** @param array<string, mixed> $options */
    private function formatter(array $options): \NumberFormatter
    {
        $formatter = new \NumberFormatter($options['html5'] ? 'en' : \Locale::getDefault(), \NumberFormatter::DECIMAL);
        if (null !== $options['scale']) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, (int) $options['scale']);
            $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, (int) $options['rounding_mode']);
        }
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, (int) (bool) $options['grouping']);

        return $formatter;
    }
}
