<?php

namespace Base\Database\Repository\Finder;

/**
 * A finder call read by the Parser: the neutral form of the finder language, which
 * a persistence compiles into its own queries.
 *
 * - $method, $arguments: the internal request the name leads to (__findBy,
 *   __findOneBy, __findLastBy, __findAtMostBy, __findPreviousBy, __findNextBy,
 *   __findRandomlyBy, __findAll, __countBy, __distinctCountBy, __lengthOfBy...)
 *   and its arguments: the criteria (those the name declares merged into those
 *   given, then the specials' numbers and keys as "special:..."), then the order,
 *   the limit, the offset, the grouping and the selected expressions;
 * - $criteria, $options: the criteria the name declares, "field:index" => value,
 *   and their options, "field:index" => list of words;
 * - $separator: And or Or, how the criteria combine (never both);
 * - $column: the column counted or measured (countFor<Column>By, lengthOf<Column>By), null when the name has none;
 * - $cacheable: a cache... request outside the command line;
 * - $eagerly: the associations an ...Eagerly... request loads, false otherwise;
 * - $request: find, count (distinctCount too) or lengthOf; $special: One, All,
 *   Randomly, AtMost, Last, LastOne, Previous, PreviousOne, Next, NextOne, or null.
 */
final class Query
{
    /**
     * @param list<mixed>                 $arguments
     * @param array<string, mixed>        $criteria
     * @param array<string, list<string>> $options
     * @param array<int|string, mixed>|bool $eagerly
     */
    public function __construct(
        public readonly string $method,
        public readonly array $arguments,
        public readonly array $criteria,
        public readonly array $options,
        public readonly ?string $separator,
        public readonly ?string $column,
        public readonly bool $cacheable,
        public readonly array|bool $eagerly,
        public readonly string $request,
        public readonly ?string $special,
    ) {
    }

    /**
     * Every criterion of the call, in order: those of the criteria array given,
     * then those the name declares, each with its options; not the specials'
     * numbers and keys (extras()).
     *
     * @return list<Criterion>
     */
    public function criterions(): array
    {
        $criterions = [];
        foreach ($this->criteriaArgument() as $key => $value) {
            $key = (string) $key;
            if (str_starts_with($key, 'special:')) {
                continue;
            }
            if (\array_key_exists($key, $this->criteria)) {
                [$field, $index] = explode(Parser::SEPARATOR, $key, 2) + [1 => null];
                $criterions[] = new Criterion($field, null === $index ? null : (int) $index, $value, array_values($this->options[$key] ?? []));
            } else {
                $criterions[] = new Criterion($key, null, $value);
            }
        }

        return $criterions;
    }

    /**
     * The specials' numbers and keys: atMost, last, prev, next, prevKeys, nextKeys.
     *
     * @return array<string, mixed>
     */
    public function extras(): array
    {
        $extras = [];
        foreach ($this->criteriaArgument() as $key => $value) {
            if (str_starts_with((string) $key, 'special:')) {
                $extras[substr((string) $key, \strlen('special:'))] = $value;
            }
        }

        return $extras;
    }

    /** @return array<int|string, mixed> the criteria argument, none for findAll */
    private function criteriaArgument(): array
    {
        if (str_starts_with($this->method, '__'.Parser::REQUEST_FIND.Parser::SPECIAL_ALL)) {
            return [];
        }

        return \is_array($this->arguments[0] ?? null) ? $this->arguments[0] : [];
    }
}
