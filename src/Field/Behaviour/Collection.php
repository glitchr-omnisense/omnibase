<?php

namespace Base\Field\Behaviour;

use Base\Field\Kind;

/**
 * A list of entries drawn one under the other (Kind::COLLECTION, Kind::ARRAY; plan "omnibase agnostic", step
 * F5d, slice 7), as omnibase's CollectionType and ArrayType decide it, on every host. They ask it:
 *
 * - options(): their defaults (CollectionType's, ArrayType's over them for an array) and what their
 *   normalizers made of them - a fixed length (an array's pattern, its `{n}` counted) neither adds nor
 *   deletes, an entry carries no label of its own, a collection is required;
 * - length(): an array's length, read from its pattern;
 * - window(): the entries a page shows of a capped collection (max_entries from entry_offset), the others
 *   kept aside and never removed by a submission that did not show them;
 * - rendered(): the indexes a windowed page says it drew (its `_rendered` input);
 * - empty(): the markup a page shows for no entry (collection2_empty);
 * - entries(): what is sent, read as the list the data keeps (an array's values in order, an associative
 *   one's key => value; `_rendered` left out).
 */
final class Collection
{
    /** The defaults of CollectionType (the entry a hidden input, entries neither added nor kept apart). */
    public const DEFAULTS = [
        'form2' => false, 'length' => 0, 'max_entries' => null, 'entry_offset' => 0, 'allow_object' => false,
        'allow_add' => false, 'allow_delete' => true, 'html' => false, 'href' => null, 'prototype' => true,
        'prototype_data' => null, 'prototype_name' => '__prototype__', 'group' => true, 'checkbox' => false,
        'row_group' => true, 'entry_collapsed' => true, 'entry_type' => Kind::HIDDEN, 'entry_options' => [],
        'entry_required' => null, 'delete_empty' => false,
    ];

    /** What ArrayType sets over them: a text per entry, added and deleted, its script form.array. */
    public const ARRAY_DEFAULTS = [
        'target' => null, 'associative' => null, 'allow_add' => true, 'allow_delete' => true, 'entry_type' => Kind::TEXT,
        'webpack_entry' => 'form.array', 'pattern' => null, 'length' => 0, 'placeholder' => [],
    ];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function options(array $options, string $kind = Kind::COLLECTION): array
    {
        $options = array_replace(self::DEFAULTS, Kind::ARRAY === $kind ? self::ARRAY_DEFAULTS : [], $options);
        if (Kind::ARRAY === $kind) {
            if (null !== $options['pattern'] && null !== $options['target']) {
                throw new \InvalidArgumentException('Option "target" cannot be set at the same time as "pattern"');
            }
            $options['target'] = null;
            $options['length'] = self::length($options);
        }
        $fixed = 0 != $options['length'];
        $options['allow_add'] = !$fixed && (bool) $options['allow_add'];
        $options['allow_delete'] = !$fixed && (bool) $options['allow_delete'];
        $options['entry_options'] = ['block_name' => 'entry', 'label' => false] + (array) $options['entry_options'];
        $options['entry_options']['label'] = false;
        $options['entry_options']['block_name'] = 'entry';
        $options['required'] = true;

        return $options;
    }

    /** @param array<string, mixed> $options */
    public static function length(array $options): int|string
    {
        return \is_string($options['pattern'] ?? null) && '' !== $options['pattern'] ? (int) preg_match_all('/\{[0-9]*\}/i', $options['pattern']) : ($options['length'] ?? 0);
    }

    /**
     * The entries a page shows: all of them, or a capped collection's window (CollectionType's max_entries,
     * on a page asked for; a submission builds them all).
     *
     * @param array<int|string, mixed> $entries
     * @param array<string, mixed>     $options
     *
     * @return array<int|string, mixed>
     */
    public static function window(array $entries, array $options): array
    {
        $max = $options['max_entries'] ?? null;
        $offset = max(0, (int) ($options['entry_offset'] ?? 0));
        if (!\is_int($max) || $max <= 0 || (0 === $offset && \count($entries) <= $max)) {
            return $entries;
        }

        return \array_slice($entries, $offset, $max, true);
    }

    /** @return array<int, true> the indexes a windowed page drew, from its `_rendered` input */
    public static function rendered(mixed $raw): array
    {
        $rendered = [];
        foreach (explode(',', \is_string($raw) ? $raw : '') as $piece) {
            $piece = trim($piece);
            if ('' !== $piece && ctype_digit($piece)) {
                $rendered[(int) $piece] = true;
            }
        }

        return $rendered;
    }

    /** collection2_empty: what a page shows of a collection without an entry. */
    public static function empty(): string
    {
        return '<div class="empty collection-empty"><div class="form-text">'.htmlspecialchars(self::trans('@fields.collection.empty'), \ENT_QUOTES).'</div></div>';
    }

    /**
     * What is sent for a list (an array's texts, a collection's hidden values), as the data keeps it: in its
     * order, `_rendered` left out; an associative array's entries ({key, value}) as key => value.
     *
     * @param array<string, mixed> $options
     *
     * @return array<int|string, mixed>
     */
    public static function entries(mixed $submitted, array $options): array
    {
        if (!\is_array($submitted)) {
            return [];
        }
        unset($submitted['_rendered']);
        if ($options['associative'] ?? false) {
            $map = [];
            foreach ($submitted as $entry) {
                if (\is_array($entry) && null !== ($entry['key'] ?? null) && '' !== $entry['key']) {
                    $map[(string) $entry['key']] = $entry['value'] ?? null;
                }
            }

            return $map;
        }
        $list = [];
        foreach ($submitted as $entry) {
            if (\is_array($entry)) {
                continue; // a part sent where a value is kept: not this list's
            }
            if (true === ($options['delete_empty'] ?? false) && (null === $entry || '' === $entry)) {
                continue;
            }
            $list[] = $entry;
        }

        return $list;
    }

    /** A list's values as a page shows them: in order, an associative one's as {key, value}. @return array<int|string, mixed> */
    public static function shown(mixed $value, array $options): array
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }
        if (\is_string($value) && \function_exists('is_serialized') && is_serialized($value)) {
            $value = unserialize($value);
        }
        $value = null === $value ? [] : (\is_array($value) ? $value : [$value]);
        if ($options['associative'] ?? false) {
            $entries = [];
            foreach ($value as $key => $entry) {
                $entries[] = ['key' => \is_array($entry) ? ($entry['key'] ?? null) : $key, 'value' => \is_array($entry) ? ($entry['value'] ?? null) : $entry];
            }

            return $entries;
        }

        return Kind::ARRAY === ($options['kind'] ?? null) ? array_values($value) : $value;
    }

    private static function trans(string $key): string
    {
        $translations = \Omnibridge\Runtime::find(\Omnibridge\Contract\Translations::class);

        return $translations instanceof \Omnibridge\Contract\Translations ? $translations->trans($key) : $key;
    }
}
