<?php

namespace Base\Form\Definition;

use Base\Field\Behaviour\Behaviours;
use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Omnibridge\Contract\Violation;
use Omnibridge\Persistence\UnitOfWork;

/**
 * Data binding, neutral: what a form sends (an array of strings, files, lists)
 * into the data it edits (an array, an entity, a DTO), and that data back
 * into the values a form shows. The kind of each field says how (Kind): a
 * trimmed string, an integer, a date, a choice among the field's choices, an
 * association by its id (through the UnitOfWork's references), a file as the
 * host gives it.
 *
 * Symfony Form's behaviour where a form relies on it: an empty string is
 * null (or the field's empty data), a missing checkbox is false, a disabled
 * field keeps the data's value, a value that cannot be read ("abc" for an
 * integer, a choice not offered) is a violation of that field - with
 * Symfony's own message - and leaves the data untouched, the data is written
 * only where its value changes.
 *
 * Transformers are plain callables: per kind (withTransformer()), or per field
 * with the custom options OPTION_REVERSE (submitted => data) and
 * OPTION_TRANSFORM (data => shown); a reverse transformer throws
 * \UnexpectedValueException for a value it cannot read.
 *
 * A kind's behaviour (Base\Field\Behaviour\Behaviours, the same object the
 * Symfony type reads) reads first, with the field's options completed by its
 * defaults: a colour shortened, a date in the picker's format, a localized
 * number, a select's part of a compound name; the kind's generic rule then
 * reads what it gives (a string to a date, to a number).
 */
final class FormBinder
{
    /** Custom option (FieldDescriptor::setCustomOption()): callable(mixed $submitted, FieldDescriptor): mixed */
    public const OPTION_REVERSE = 'binder.reverse';

    /** Custom option: callable(mixed $data, FieldDescriptor): mixed */
    public const OPTION_TRANSFORM = 'binder.transform';

    /** Symfony's invalid messages, by kind (its types' invalid_message). */
    public const INVALID_MESSAGES = [
        Kind::INTEGER => 'Please enter an integer.',
        Kind::YEAR => 'Please enter an integer.',
        Kind::STOCK => 'Please enter an integer.',
        Kind::NUMBER => 'Please enter a number.',
        Kind::MONEY => 'Please enter a valid money amount.',
        Kind::FOREX => 'Please enter a valid money amount.',
        Kind::DATE => 'Please enter a valid date.',
        Kind::DATETIME => 'Please enter a valid date and time.',
        Kind::DATETIME_PICKER => 'Please enter a valid date and time.',
        Kind::TIME => 'Please enter a valid time.',
        Kind::CHOICE => 'The selected choice is invalid.',
        Kind::COUNTRY => 'Please select a valid country.',
        Kind::CURRENCY => 'Please select a valid currency.',
        Kind::LOCALE => 'Please select a valid locale.',
        Kind::EMAIL => 'Please enter a valid email address.',
        Kind::URL => 'Please enter a valid URL.',
        Kind::FILE => 'Please select a valid file.',
        Kind::IMAGE => 'Please select a valid file.',
        Kind::REPEATED => 'The values do not match.',
    ];

    public const INVALID_MESSAGE = 'This value is not valid.';

    /** The kinds whose submitted string is kept as it is sent, without trimming (Symfony's trim: false). */
    public const UNTRIMMED_KINDS = [Kind::PASSWORD];

    /** @var array<string, array{0: callable, 1: callable|null}> */
    private array $transformers = [];

    public function __construct(private readonly ?UnitOfWork $unitOfWork = null)
    {
    }

    /**
     * A copy reading a kind with these callables: $reverse(submitted, field)
     * gives the data's value, $transform(data, field) the value shown.
     */
    public function withTransformer(string $kind, callable $reverse, ?callable $transform = null): self
    {
        $binder = clone $this;
        $binder->transformers[$kind] = [$reverse, $transform];

        return $binder;
    }

    /**
     * The values the form shows for this data: one per field, by name - a
     * string, a list of strings (a multiple choice), a bool (a checkbox), the
     * host's file, an array (a collection) or null.
     *
     * @return array<string, mixed>
     */
    public function read(FormDefinition $form, mixed $data): array
    {
        $values = [];
        foreach ($form->getFields() as $name => $field) {
            if (\in_array($field->getKind(), Kind::UNMAPPED_KINDS, true)) {
                continue;
            }
            if (\array_key_exists('data', $field->getOptions())) {
                $value = $field->getOption('data');
            } else {
                $value = $this->isMapped($field) && null !== $data ? $this->getValue($data, $this->path($name, $field)) : $field->getValue();
            }
            $values[$name] = $this->transform($value, $field);
        }

        return $values;
    }

    /**
     * Binds what is submitted to the data: the data with the values written
     * (the same object, or the array completed), the values to show again
     * (as sent, for a field that could not be read), and the violations of
     * the fields that could not be read.
     *
     * @param array<string, mixed> $submitted the form's part of the request (its name's key), files merged in
     */
    public function bind(FormDefinition $form, array $submitted, mixed $data = null): Binding
    {
        $data ??= null !== $form->getDataClass() && class_exists($form->getDataClass()) ? new ($form->getDataClass())() : [];
        $submitted = $form->rewrite($submitted);
        $values = [];
        $violations = [];

        foreach ($form->getFields() as $name => $field) {
            $kind = $field->getKind();
            if (\in_array($kind, Kind::UNMAPPED_KINDS, true)) {
                continue;
            }

            $path = $this->path($name, $field);
            $mapped = $this->isMapped($field);
            if (true === $field->isDisabled()) {
                $values[$name] = $this->transform($mapped ? $this->getValue($data, $path) : $field->getValue(), $field);
                continue;
            }

            $raw = $submitted[$name] ?? null;
            try {
                $value = $this->reverse($raw, $field);
            } catch (\UnexpectedValueException) {
                $violations[] = new Violation(Kind::REPEATED === $kind ? $name.'.'.($field->getOption('first_name') ?? 'first') : $name, (string) ($field->getOption('invalid_message') ?? self::INVALID_MESSAGES[$kind] ?? self::INVALID_MESSAGE), 'invalid', ['{{ value }}' => \is_scalar($raw) ? (string) $raw : '']);
                $values[$name] = \is_scalar($raw) || \is_array($raw) ? $raw : null;
                continue;
            }

            $values[$name] = Kind::isFile($kind) ? null : $this->transform($value, $field);
            if (!$mapped) {
                $field->setValue($value);
                continue;
            }
            if (Kind::isFile($kind) && null === $value) {
                continue; // no file sent: the data keeps its own
            }
            if (\is_array($data)) {
                $data[$path] = $value;
            } elseif ($this->getValue($data, $path) !== $value) {
                Properties::set($data, $path, $value);
            }
        }

        return new Binding($data, $values, $violations);
    }

    /** The submitted value of one field read as the data keeps it; throws \UnexpectedValueException when it cannot be. */
    public function reverse(mixed $raw, FieldDescriptor $field): mixed
    {
        $kind = (string) $field->getKind();
        $custom = $field->getCustomOption(self::OPTION_REVERSE) ?? $this->transformers[$kind][0] ?? null;
        if (\is_callable($custom)) {
            return $custom($raw, $field);
        }
        if (null !== $behaviour = Behaviours::ofField($field)) {
            $field = Behaviours::complete($field);
            $raw = $behaviour->reverse($raw, $field->getFormTypeOptions());
        }

        if (Kind::BOOLEAN === $kind || Kind::CHECKBOX === $kind) {
            return null !== $raw && false !== $raw && '0' !== $raw && '' !== $raw;
        }
        if (Kind::REPEATED === $kind) {
            return $this->repeated($raw, $field);
        }
        if (Kind::isFile($kind)) {
            return '' === $raw ? null : $raw;
        }
        if (Kind::isChoice($kind)) {
            return $this->choice($raw, $field);
        }
        if (\is_array($raw)) {
            if (\in_array($kind, [Kind::ARRAY, Kind::COLLECTION, Kind::ATTRIBUTE, Kind::TRANSLATION, Kind::CUSTOM], true)) {
                return $raw;
            }
            throw new \UnexpectedValueException('A list was sent for a single value.');
        }

        if (\is_string($raw) && !\in_array($kind, self::UNTRIMMED_KINDS, true) && false !== $field->getOption('trim')) {
            $raw = trim($raw);
        }
        if (null === $raw || '' === $raw) {
            // An empty string the definition gives as its empty data stays a string, as Symfony's TextType
            // keeps it (a setter typed string: App\Entity\User::setUsername()); a host that sends nothing as
            // null (Laravel's ConvertEmptyStringsToNull) gets it too.
            if ('' === $field->getEmptyData()) {
                return '';
            }
            $empty = $field->getEmptyData() ?? $field->getOption('empty_data');

            return $empty instanceof \Closure ? $empty() : ('' === $empty ? null : $empty);
        }

        return match ($kind) {
            Kind::INTEGER, Kind::YEAR, Kind::STOCK => $this->integer($raw),
            Kind::NUMBER, Kind::MONEY, Kind::FOREX => $this->number($raw, $field),
            // '!': what is not typed is zero, as Symfony reads it (midnight, no seconds), not the clock's.
            Kind::DATE => $this->date($raw, $field, ['!Y-m-d']),
            Kind::DATETIME, Kind::DATETIME_PICKER => $this->date($raw, $field, ['!Y-m-d\TH:i:s', '!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d H:i']),
            Kind::TIME => $this->date($raw, $field, ['!H:i:s', '!H:i']),
            default => \is_scalar($raw) ? (string) $raw : $raw,
        };
    }

    /** The data's value of one field as the form shows it. */
    public function transform(mixed $value, FieldDescriptor $field): mixed
    {
        $kind = (string) $field->getKind();
        $custom = $field->getCustomOption(self::OPTION_TRANSFORM) ?? $this->transformers[$kind][1] ?? null;
        if (\is_callable($custom)) {
            return $custom($value, $field);
        }
        if (null !== $behaviour = Behaviours::ofField($field)) {
            $field = Behaviours::complete($field);
            $value = $behaviour->transform($value, $field->getFormTypeOptions());
        }

        if (Kind::BOOLEAN === $kind || Kind::CHECKBOX === $kind) {
            return (bool) $value;
        }
        if (null === $value) {
            return true === $field->getOption('multiple') ? [] : null;
        }
        if (Kind::isFile($kind)) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(match ($kind) {
                Kind::DATE => 'Y-m-d',
                Kind::TIME => 'H:i',
                default => 'Y-m-d\TH:i',
            });
        }
        // A list, or a collection of records (a translations' or an association's field): each item read.
        if (is_iterable($value) && (Kind::isChoice($kind) || true === $field->getOption('multiple') || !\is_array($value))) {
            $list = [];
            foreach ($value as $item) {
                $list[] = $this->scalar($item);
            }

            return $list;
        }
        if (\is_array($value)) {
            return $value;
        }

        return $this->scalar($value);
    }

    /** Both inputs say the same: that value, read by the inputs' own kind; otherwise it cannot be read. */
    private function repeated(mixed $raw, FieldDescriptor $field): mixed
    {
        $first = \is_array($raw) ? ($raw[$field->getOption('first_name') ?? 'first'] ?? null) : null;
        $second = \is_array($raw) ? ($raw[$field->getOption('second_name') ?? 'second'] ?? null) : null;
        if (($first ?? '') !== ($second ?? '')) {
            throw new \UnexpectedValueException('The two values differ.');
        }

        return null === $first || '' === $first ? null : $this->reverse($first, (clone $field)->setKind((string) ($field->getOption('type') ?? Kind::TEXT)));
    }

    private function scalar(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_scalar($value) || $value instanceof \Stringable && !$this->isEntity($value)) {
            return (string) $value;
        }
        if (\is_object($value)) {
            try {
                $id = null !== $this->unitOfWork ? $this->unitOfWork->identifier($value) : null;
            } catch (\Throwable) {
                $id = null; // not a record the unit of work maps
            }
            $id ??= method_exists($value, 'getId') ? $value->getId() : null;

            return null !== $id ? (string) $id : null;
        }

        return $value;
    }

    private function isEntity(object $value): bool
    {
        return method_exists($value, 'getId');
    }

    private function integer(mixed $raw): int
    {
        if (\is_int($raw)) {
            return $raw;
        }
        if (!\is_string($raw) || !preg_match('/^[+-]?\d+$/', $raw)) {
            throw new \UnexpectedValueException('Not an integer.');
        }

        return (int) $raw;
    }

    private function number(mixed $raw, FieldDescriptor $field): float|int|string
    {
        if (\is_int($raw) || \is_float($raw)) {
            return $raw;
        }
        $normalised = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], (string) $raw);
        if (!is_numeric($normalised)) {
            throw new \UnexpectedValueException('Not a number.');
        }
        if ('string' === $field->getOption('input')) {
            return $normalised;
        }
        $scale = $field->getOption('scale');

        return \is_int($scale) ? round((float) $normalised, $scale) : (float) $normalised;
    }

    /** @param list<string> $formats */
    private function date(mixed $raw, FieldDescriptor $field, array $formats): \DateTimeInterface|string
    {
        $input = $field->getOption('input') ?? 'datetime';
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, (string) $raw);
            if (false !== $date && 0 === (\DateTimeImmutable::getLastErrors() ?: ['warning_count' => 0])['warning_count']) {
                return match ($input) {
                    'datetime_immutable' => $date,
                    'string' => $date->format($field->getOption('input_format') ?? 'Y-m-d'),
                    'timestamp' => (string) $date->getTimestamp(),
                    default => \DateTime::createFromImmutable($date),
                };
            }
        }

        throw new \UnexpectedValueException('Not a date.');
    }

    /** A choice among the field's choices (its option `choices`, label => value, groups allowed); any value when it has none. */
    private function choice(mixed $raw, FieldDescriptor $field): mixed
    {
        $multiple = true === $field->getOption('multiple');
        if ($multiple) {
            $list = null === $raw || '' === $raw ? [] : (array) $raw;
            $values = [];
            foreach ($list as $item) {
                if (null === $item || '' === $item) {
                    continue;
                }
                if (!\is_scalar($item)) {
                    throw new \UnexpectedValueException('A choice is not a string.');
                }
                $values[] = $this->offered((string) $item, $field);
            }

            return $values;
        }

        if (\is_array($raw)) {
            throw new \UnexpectedValueException('A list was sent for a single choice.');
        }
        if (null === $raw || '' === $raw) {
            return null;
        }

        return $this->offered((string) $raw, $field);
    }

    /** A field's choices, a callable called once it is asked (a query's answer); null without any. */
    public static function choices(FieldDescriptor $field): ?iterable
    {
        $choices = $field->getOption('choices');
        if ($choices instanceof \Closure || \is_array($choices) && \is_callable($choices)) {
            $choices = $choices();
        }

        return is_iterable($choices) ? $choices : null;
    }

    private function offered(string $raw, FieldDescriptor $field): mixed
    {
        if ((Kind::ASSOCIATION === $field->getKind() || null !== $field->getOption('class')) && null === $field->getOption('choices')) {
            $class = $field->getOption('class');
            if (\is_string($class) && null !== $this->unitOfWork) {
                return $this->unitOfWork->reference($class, ctype_digit($raw) ? (int) $raw : $raw);
            }

            return $raw;
        }

        $choices = self::choices($field);
        if (null === $choices) {
            return $raw;
        }
        foreach (self::flatten($choices) as $value) {
            if ($this->scalar($value) === $raw) {
                return $value;
            }
        }

        throw new \UnexpectedValueException('Not one of the choices.');
    }

    /**
     * The values of a choices list, groups flattened.
     *
     * @return list<mixed>
     */
    public static function flatten(iterable $choices): array
    {
        $values = [];
        foreach ($choices as $value) {
            if (\is_array($value)) {
                array_push($values, ...self::flatten($value));
            } else {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function isMapped(FieldDescriptor $field): bool
    {
        return true !== $field->isVirtual() && false !== $field->getOption('mapped');
    }

    private function path(string $name, FieldDescriptor $field): string
    {
        $path = $field->getOption('property_path');

        return \is_string($path) && '' !== $path ? $path : $name;
    }

    private function getValue(mixed $data, string $path): mixed
    {
        if (\is_array($data)) {
            return $data[$path] ?? null;
        }
        if (!\is_object($data)) {
            return null;
        }

        try {
            return Properties::get($data, $path);
        } catch (\UnexpectedValueException) {
            return null;
        }
    }
}
