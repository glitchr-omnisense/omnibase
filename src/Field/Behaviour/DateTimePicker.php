<?php

namespace Base\Field\Behaviour;

/**
 * Kind::DATETIME_PICKER: a date (and time) typed or picked in flatpickr. Its
 * value is shown and read in the field's ICU `format` (yyyy-MM-dd HH:mm by
 * default; a date-only picker gives yyyy-MM-dd), the picker's settings in
 * `datetimepicker`, merged over the defaults key by key: a caller that turns
 * the time off keeps the page's language.
 */
final class DateTimePicker extends AbstractBehaviour
{
    public const FORMAT = 'yyyy-MM-dd HH:mm';

    protected function defaults(): array
    {
        return [
            'format' => self::FORMAT,
            'html5' => false,
            'widget' => 'single_text',
            'required' => false,
            'webpack_entry' => 'form.datetime',
            'debug' => false,
            'datetimepicker' => self::picker(),
        ];
    }

    /**
     * The picker's settings: the given ones over the defaults, key by key.
     *
     * @return array<string, mixed>
     */
    public static function picker(mixed $given = [], ?string $language = null): array
    {
        return array_merge(['enableTime' => true, 'locale' => $language ?? self::language(), 'dateFormat' => 'Y-m-d H:i'], \is_array($given) ? $given : []);
    }

    public function options(array $given): array
    {
        $options = parent::options($given);
        $options['datetimepicker'] = self::picker($given['datetimepicker'] ?? []);

        return $options;
    }

    public function vars(array $options, mixed $shown, string $id): array
    {
        return ['datetimepicker' => json_encode($options['datetimepicker'])];
    }

    protected function controller(): ?string
    {
        return 'datetimepicker';
    }

    /** A date written in the field's format, as Symfony's DateTimeType writes it (IntlDateFormatter). */
    public function transform(mixed $data, array $options): mixed
    {
        if (!$data instanceof \DateTimeInterface) {
            return $data;
        }

        return self::formatter((string) $options['format'], $data->getTimezone())->format($data);
    }

    /**
     * What is typed, in the field's format, as the binder reads a date and
     * time (Y-m-d H:i:s); refused when it is not in that format.
     */
    public function reverse(mixed $submitted, array $options): mixed
    {
        if (!\is_string($submitted) || '' === trim($submitted)) {
            return $submitted;
        }
        $formatter = self::formatter((string) $options['format'], new \DateTimeZone(date_default_timezone_get()));
        $position = 0;
        $timestamp = $formatter->parse(trim($submitted), $position);
        if (false === $timestamp || $position !== \strlen(trim($submitted))) {
            throw new \UnexpectedValueException(sprintf('"%s" is not a date in the format %s.', $submitted, $options['format']));
        }

        return (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }

    private static function formatter(string $pattern, \DateTimeZone $timezone): \IntlDateFormatter
    {
        $formatter = new \IntlDateFormatter(\Locale::getDefault(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, $timezone->getName(), \IntlDateFormatter::GREGORIAN, $pattern);
        $formatter->setLenient(false);

        return $formatter;
    }
}
