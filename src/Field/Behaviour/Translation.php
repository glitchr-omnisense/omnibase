<?php

namespace Base\Field\Behaviour;

use Base\Database\Entity\Extension\TranslatableInterface;
use Base\Database\Entity\Extension\TranslationInterface;
use Base\Field\FieldDescriptor;
use Omnibridge\Contract\Locales;
use Omnibridge\Runtime;

/**
 * A translations field (Kind::TRANSLATION, plan "omnibase agnostic", step F5d), as omnibase's
 * TranslationType builds it, on every host: one part per language, each the translation entity's
 * fields (RecordFields, the entity's shape). What TranslationType decides itself is here, and it asks it:
 *
 * - options(): its defaults - the page's language, the default one, the languages offered (the Locales
 *   contract), the required ones among them;
 * - locales(): the languages drawn, the default one alone for a single-language field (the record's one
 *   language when it has exactly one);
 * - entryRequired(): whether a language's part is required (the default one, the required ones);
 * - perLocale(): each language's fields, a field's `locale_options` applied (or the field left out where
 *   they say display: false);
 * - required(): a field's `required` given as a callable ($required, $locale, $options), called.
 *
 * And for the hosts without Symfony's forms (the neutral FormView and FormBinder): fields(), each
 * language's FieldDescriptors; read(), a record's translations as the values shown; bind(), what is sent
 * written into the record's translations (TranslatableInterface::translate()).
 */
final class Translation
{
    private function __construct()
    {
    }

    /**
     * The options with TranslationType's defaults: locale, single_locale, default_locale, required_locales
     * (only the offered ones), available_locales, autoload, fields, excluded_fields, translation_class,
     * translatable_class, multiple.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function options(array $options): array
    {
        $locales = Runtime::find(Locales::class);
        $default = $locales instanceof Locales ? $locales->getDefaultLocale() : \Locale::getDefault();
        $options += [
            'locale' => $locales instanceof Locales ? $locales->getLocale() : $default,
            'locale_options' => [],
            'single_locale' => false,
            'default_locale' => $default,
            'required_locales' => [],
            'available_locales' => $locales instanceof Locales ? $locales->getAvailableLocales() : [$default],
            'autoload' => true,
            'fields' => [],
            'excluded_fields' => [],
            'translation_class' => null,
            'translatable_class' => null,
            'multiple' => false,
        ];
        $options['required_locales'] = self::requiredLocales((array) $options['required_locales'], (array) $options['available_locales']);

        return $options;
    }

    /**
     * TranslationType's normalizer: the required languages among those offered.
     *
     * @param list<string> $required
     * @param list<string> $available
     *
     * @return list<string>
     */
    public static function requiredLocales(array $required, array $available): array
    {
        return array_intersect($required, $available);
    }

    /**
     * The languages drawn, each with the language whose fields its part takes, and the default one they
     * are measured against: every offered language, each its own; for a single-language field, the
     * default one - or the record's one language when it has exactly one, with the default's fields.
     *
     * @param array<string, mixed> $options       completed (options())
     * @param list<string>         $recordLocales the languages the record has a translation in
     *
     * @return array{locales: array<string, string>, default: string} drawn => the language of its fields
     */
    public static function locales(array $options, array $recordLocales): array
    {
        $default = (string) $options['default_locale'];
        if ($options['single_locale']) {
            if (1 === \count($recordLocales)) {
                $only = (string) array_values($recordLocales)[0];

                return ['locales' => [$only => $default], 'default' => $only];
            }

            return ['locales' => [$default => $default], 'default' => $default];
        }
        $locales = array_map('strval', (array) $options['available_locales']);

        return ['locales' => array_combine($locales, $locales), 'default' => $default];
    }

    /**
     * Whether a language's part is required: the default one and the required ones are; the others are
     * not when the required ones are named; null (the part's own default, required) otherwise.
     *
     * @param array<string, mixed> $options completed (options())
     */
    public static function entryRequired(string $locale, string $default, array $options): ?bool
    {
        if ($locale === $default || \in_array($locale, $options['required_locales'], true)) {
            return true;
        }
        if (!empty($options['required_locales'])) {
            return \in_array($locale, $options['required_locales'], true);
        }

        return null;
    }

    /**
     * Each language's fields: a field's `locale_options` (by language) over its own, a field left out of a
     * language whose options say display: false.
     *
     * @param array<string, array<string, mixed>> $fields  name => options
     * @param list<string>                        $locales the offered languages
     *
     * @return array<string, array<string, array<string, mixed>>> locale => name => options
     */
    public static function perLocale(array $fields, array $locales): array
    {
        $translationFields = [];
        foreach ($fields as $fieldName => $fieldConfig) {
            if (!isset($fieldConfig['locale_options'])) {
                foreach ($locales as $locale) {
                    $translationFields[$locale][$fieldName] = $fieldConfig;
                }
                continue;
            }

            $localesFieldOptions = $fieldConfig['locale_options'];
            unset($fieldConfig['locale_options']);
            foreach ($locales as $locale) {
                $localeFieldOptions = $localesFieldOptions[$locale] ?? [];
                if (!isset($localeFieldOptions['display']) || (true === $localeFieldOptions['display'])) {
                    $translationFields[$locale][$fieldName] = $localeFieldOptions + $fieldConfig;
                }
            }
        }

        return $translationFields;
    }

    /**
     * A field's options with its `required` given as a callable called: fn (bool $required, string
     * $locale, array $options): bool, told whether the language's part is required.
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function required(array $field, bool $entryRequired, string $locale, array $options): array
    {
        if (!\array_key_exists('required', $field) || !$field['required'] instanceof \Closure) {
            return $field;
        }
        $function = new \ReflectionFunction($field['required']);
        if (3 != $function->getNumberOfParameters()) {
            throw new \InvalidArgumentException('required-callable method is expecting 3 arguments: "$value", "$locale", "$options"');
        }
        [$required, $language, $all] = $function->getParameters();
        if ('bool' != $required->getType()) {
            throw new \InvalidArgumentException('$value parameter in "required" closure must be a boolean');
        }
        if ('string' != $language->getType()) {
            throw new \InvalidArgumentException('$locale parameter in "required" closure must be a string');
        }
        if ('array' != $all->getType()) {
            throw new \InvalidArgumentException('$options parameter in "required" closure must be an array');
        }
        if ('bool' != $function->getReturnType()) {
            throw new \InvalidArgumentException('Return type of "required" closure must be a boolean');
        }
        $field['required'] = $field['required']($entryRequired, $locale, $options);

        return $field;
    }

    /**
     * The translation entity's class of a field: its `translation_class`, else its `translatable_class`'s,
     * else the record's (the form's data class); null when none is translatable.
     *
     * @param array<string, mixed> $options
     */
    public static function translationClass(array $options, ?string $dataClass): ?string
    {
        $translation = $options['translation_class'] ?? null;
        if (\is_string($translation) && is_subclass_of($translation, TranslationInterface::class)) {
            return $translation;
        }
        foreach ([$options['translatable_class'] ?? null, $dataClass] as $translatable) {
            if (\is_string($translatable) && is_subclass_of($translatable, TranslatableInterface::class)) {
                return $translatable::getTranslationEntityClass();
            }
        }

        return null;
    }

    /**
     * Each language's fields as descriptors, as TranslationType adds them: the translation entity's fields
     * (RecordFields), each language's options (perLocale()), a callable `required` called, and the field
     * required only where the language's part is (a part not required makes its fields optional).
     *
     * @param array<string, mixed> $options completed (options())
     * @param list<string>         $recordLocales
     *
     * @return array<string, array<string, FieldDescriptor>> locale => name => descriptor
     */
    public static function fields(string $translationClass, array $options, array $recordLocales = []): array
    {
        $fields = RecordFields::of($translationClass, (array) $options['fields'], (array) $options['excluded_fields']);
        if (!$options['autoload']) {
            $fields = array_filter($fields, static fn (string $name): bool => \array_key_exists($name, (array) $options['fields']), \ARRAY_FILTER_USE_KEY);
        }
        $perLocale = self::perLocale($fields, (array) $options['available_locales']);
        ['locales' => $locales, 'default' => $default] = self::locales($options, $recordLocales);

        $descriptors = [];
        foreach ($locales as $locale => $fieldsLocale) {
            if (!isset($perLocale[$fieldsLocale])) {
                continue;
            }
            $entry = self::entryRequired($locale, $default, $options);
            foreach ($perLocale[$fieldsLocale] as $name => $field) {
                $field = self::required($field, $entry ?? false, $locale, ['locale' => $locale] + $options);
                if (null === $descriptor = RecordFields::descriptor($translationClass, $name, $field)) {
                    continue;
                }
                if (false === $entry) {
                    $descriptor->setRequired(false);
                }
                $descriptors[$locale][$name] = $descriptor;
            }
        }

        return $descriptors;
    }

    /**
     * The languages a record has a translation in: its translations' keys.
     *
     * @return list<string>
     */
    public static function recordLocales(mixed $translations): array
    {
        if (!is_iterable($translations)) {
            return [];
        }
        $locales = [];
        foreach ($translations as $locale => $translation) {
            $locales[] = (string) $locale;
        }

        return $locales;
    }

    /**
     * A record's translations as each language's values, read by each field's kind.
     *
     * @param array<string, array<string, FieldDescriptor>> $fields locale => name => descriptor (fields())
     *
     * @return array<string, array<string, mixed>> locale => name => value shown
     */
    public static function read(mixed $translations, array $fields, \Base\Form\Definition\FormBinder $binder): array
    {
        $values = [];
        foreach ($fields as $locale => $descriptors) {
            $translation = is_iterable($translations) ? self::of($translations, $locale) : null;
            foreach ($descriptors as $name => $descriptor) {
                try {
                    $value = $translation instanceof TranslationInterface ? \Base\Form\Definition\Properties::get($translation, $name) : null;
                } catch (\UnexpectedValueException) {
                    $value = null;
                }
                $values[$locale][$name] = $binder->transform($value, $descriptor);
            }
        }

        return $values;
    }

    /**
     * What is sent for each language written into the record's translations: a language's translation
     * (the record's, else a new one, TranslatableInterface::translate()) given each field's value; a
     * translation left empty is not kept (TranslationType's SUBMIT: an empty one is removed).
     *
     * @param array<string, mixed>                          $submitted locale => name => raw
     * @param array<string, array<string, FieldDescriptor>> $fields    locale => name => descriptor
     *
     * @return array<string, array<string, mixed>> locale => name => value shown again
     *
     * @throws \UnexpectedValueException a value that cannot be read, its path (locale.name) as message
     */
    public static function bind(TranslatableInterface $record, array $submitted, array $fields, \Base\Form\Definition\FormBinder $binder): array
    {
        $shown = [];
        foreach ($fields as $locale => $descriptors) {
            $sent = $submitted[$locale] ?? null;
            if (!\is_array($sent)) {
                continue;
            }
            $translation = self::of($record->getTranslations(), $locale) ?? $record->translate($locale);
            if (!$translation instanceof TranslationInterface) {
                continue;
            }
            foreach ($descriptors as $name => $descriptor) {
                if (true === $descriptor->isDisabled() || false === $descriptor->getOption('mapped')) {
                    continue;
                }
                try {
                    $value = $binder->reverse($sent[$name] ?? null, $descriptor);
                } catch (\UnexpectedValueException $exception) {
                    throw new \UnexpectedValueException($locale.'.'.$name, 0, $exception);
                }
                \Base\Form\Definition\Properties::set($translation, $name, $value);
                $shown[$locale][$name] = $binder->transform($value, $descriptor);
            }
            $translation->setLocale($locale);
            if ($translation->isEmpty()) {
                $record->removeTranslation($translation);
            } elseif (null === self::of($record->getTranslations(), $locale)) {
                $record->addTranslation($translation);
            }
        }

        return $shown;
    }

    /** A record's translation in a language, by its key or its own locale; null when it has none. */
    private static function of(iterable $translations, string $locale): ?TranslationInterface
    {
        foreach ($translations as $key => $translation) {
            if ($translation instanceof TranslationInterface && ((string) $key === $locale || $translation->getLocale() === $locale)) {
                return $translation;
            }
        }

        return null;
    }
}
