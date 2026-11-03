<?php

namespace Base\Form\Definition;

use Base\Field\Behaviour\AssociationFile;
use Base\Field\Behaviour\Behaviours;
use Base\Field\Behaviour\Translation;
use Base\Field\Behaviour\Upload;
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
            if (Kind::TRANSLATION === $field->getKind() && \is_object($data)) {
                // The record's translations read by its translation entity's fields.
                $field = $this->translatable($field, $data::class);
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
            if (Kind::TRANSLATION === $kind && !$field->hasExplicitFormType() && $data instanceof \Base\Database\Entity\Extension\TranslatableInterface && $mapped && $name === $path) {
                // Each language's part written into the record's translations (Translation::bind()).
                $field = $this->translatable($field, $data::class);
                try {
                    $values[$name] = Translation::bind($data, \is_array($raw) ? $raw : [], $this->translationFields($field, $data->getTranslations()), $this);
                } catch (\UnexpectedValueException $exception) {
                    $violations[] = new Violation($name.'.'.$exception->getMessage(), self::INVALID_MESSAGE, 'invalid', ['{{ value }}' => '']);
                    $values[$name] = $raw;
                }
                continue;
            }
            if (Kind::ASSOCIATION_FILE === $kind && !$field->hasExplicitFormType() && \is_object($data) && $mapped) {
                // Records made from uploads (AssociationFile): the upload field's files made into the related records.
                if (\is_array($raw) && null !== $refusal = $this->associationFile($data, $path, $raw, \Base\Field\Behaviour\Guess::from($field, $form->getDataClass() ?? $data::class))) {
                    $violations[] = new Violation($name.'.'.$refusal[2], $refusal[0], 'file', $refusal[1]);
                }
                $values[$name] = null;
                continue;
            }
            if (Kind::isFile($kind) && ($upload = Behaviours::ofField($field)) instanceof Upload
                && null !== $refusal = $upload->refusal($raw, Behaviours::complete($field)->getFormTypeOptions(), $name)) {
                // The file sent refused as FileType's constraint refuses it (its type, its size), the data untouched.
                $violations[] = new Violation($name, $refusal[0], 'file', $refusal[1]);
                $values[$name] = null;
                continue;
            }
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
            if (Kind::isFile($kind) && null === $value && !\is_array($raw)) {
                continue; // no file sent: the data keeps its own (a file field's compound sent empty is its file removed, as FileType reads it)
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
        if (Kind::TRANSLATION === $kind && !$field->hasExplicitFormType()) {
            // Each language the record is written in and the field draws: its fields' values.
            return Translation::read($value, array_intersect_key($this->translationFields($field, $value), array_flip(Translation::recordLocales($value))), $this);
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

    /**
     * Records made from uploads (AssociationFile, as AssociationFileType maps them): the upload field's part
     * read as the upload reads it, made into the related records (records()), written into the record
     * (bind()). Nothing sent under the upload's name keeps the records held; a file sent that the upload
     * refuses (Upload::refusal()) leaves them too, and is answered: its message, its parameters, the upload's name.
     *
     * @param array<string, mixed> $submitted
     *
     * @return array{0: string, 1: array<string, int|string>, 2: string}|null
     */
    private function associationFile(object $data, string $path, array $submitted, FieldDescriptor $field): ?array
    {
        $options = AssociationFile::options($field->getFormTypeOptions());
        $child = AssociationFile::child($options);
        if (!\array_key_exists($child, $submitted)) {
            return null;
        }
        $upload = FormDefinition::field($child, AssociationFile::kind($options))->setFormTypeOptions(AssociationFile::childOptions($options, true));
        $behaviour = Behaviours::ofField($upload);
        if ($behaviour instanceof Upload && null !== $refusal = $behaviour->refusal($submitted[$child], Behaviours::complete($upload)->getFormTypeOptions(), $child)) {
            return [...$refusal, $child];
        }
        $files = $this->reverse($submitted[$child], $upload);
        $hydrator = \Omnibridge\Runtime::find(\Base\Database\Entity\EntityHydratorInterface::class);
        if (!$hydrator instanceof \Base\Database\Entity\EntityHydratorInterface) {
            throw new \LogicException('Records made from uploads need omnibase\'s entity hydrator (Base\\Database\\Entity\\EntityHydratorInterface) from the host.');
        }
        AssociationFile::bind($data, $path, AssociationFile::records($files, $this->getValue($data, $path), $options, $data, $hydrator), $options);

        return null;
    }

    /** A translations field told its record's class where its options do not say it. */
    private function translatable(FieldDescriptor $field, string $class): FieldDescriptor
    {
        if (null !== $field->getOption('translation_class') || null !== $field->getOption('translatable_class')) {
            return $field;
        }

        return (clone $field)->setFormTypeOption('translatable_class', $class);
    }

    /**
     * A translations field's descriptors, by language (Translation::fields()); none when its translation
     * entity is unknown (no class given, no translation held).
     *
     * @return array<string, array<string, FieldDescriptor>>
     */
    private function translationFields(FieldDescriptor $field, mixed $translations): array
    {
        $options = Translation::options($field->getFormTypeOptions());
        $class = Translation::translationClass($options, null);
        if (null === $class && is_iterable($translations)) {
            foreach ($translations as $translation) {
                $class = \is_object($translation) ? $translation::class : null;
                break;
            }
        }

        return null !== $class ? Translation::fields($class, $options, Translation::recordLocales($translations)) : [];
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
