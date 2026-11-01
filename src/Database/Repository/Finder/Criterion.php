<?php

namespace Base\Database\Repository\Finder;

/**
 * One criterion of a finder: a field (a path, "image.source"), the value it is
 * compared with, and the words of the language that say how (EqualTo, YoungerThan,
 * Partial, Insensitive, ClosestTo, InstanceOf...). $index numbers the criteria a
 * name declares on the same field (findByIdGreaterThanAndIdLowerThan: id 0, id 1);
 * null for a criterion given in the criteria array, compared for equality (or IN
 * for a list).
 */
final class Criterion
{
    /** @param list<string> $options */
    public function __construct(
        public readonly string $field,
        public readonly ?int $index,
        public readonly mixed $value,
        public readonly array $options = [],
    ) {
    }

    public function is(string $option): bool
    {
        return \in_array($option, $this->options, true);
    }

    /** The key the criterion has in the criteria array the compilers read ("slug:0", or the field itself). */
    public function key(): string
    {
        return null === $this->index ? $this->field : $this->field.Parser::SEPARATOR.$this->index;
    }
}
