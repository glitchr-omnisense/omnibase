<?php

namespace Tests\Base\Database\Repository\Finder;

use Base\Database\Repository\Finder\Subject;

/** An entity known by its fields alone, as a persistence without Doctrine describes it. */
final class Fields implements Subject
{
    /** @param list<string> $fields */
    public function __construct(private readonly string $class, private readonly array $fields)
    {
    }

    public function getClassName(): string
    {
        return $this->class;
    }

    public function getFieldNames(): array
    {
        return $this->fields;
    }

    public function hasField(string $name): bool
    {
        return \in_array($name, $this->fields, true);
    }

    public function criteriaFromSelect(object $select): array
    {
        return array_filter(get_object_vars($select), static fn (mixed $value) => null !== $value);
    }
}
