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

    /**
     * A member of a group built from its members (Group::of(), step B1b): a field, its value and the words that say
     * how it compares (Parser::OPTION_*: EqualTo when none says it), numbered by the group.
     */
    public static function of(string $field, mixed $value, string ...$words): self
    {
        return new self($field, null, $value, array_values($words));
    }

    /** The rows of these classes or of their subclasses (the discriminator), from code that holds the classes. */
    public static function instanceOf(string ...$classes): self
    {
        return self::ofClass(Parser::OPTION_INSTANCEOF, $classes);
    }

    /** The rows of none of these classes nor of their subclasses. */
    public static function notInstanceOf(string ...$classes): self
    {
        return self::ofClass(Parser::OPTION_NOT_INSTANCEOF, $classes);
    }

    /** The rows of exactly these classes. */
    public static function classOf(string ...$classes): self
    {
        return self::ofClass(Parser::OPTION_CLASSOF, $classes);
    }

    /** The rows of any class but exactly these. */
    public static function notClassOf(string ...$classes): self
    {
        return self::ofClass(Parser::OPTION_NOT_CLASSOF, $classes);
    }

    /**
     * The rows some record of $class points to through its association $pointingTo (and matching $criteria): EXISTS.
     * An entry of a criteria array: `[..., Criterion::exists(HourCredit::class, 'timeEntry')]`.
     *
     * @param array<int|string, mixed> $criteria
     */
    public static function exists(string $class, string $pointingTo, array $criteria = []): self
    {
        return new self(self::EXISTS, null, [$class, $pointingTo, $criteria], [self::EXISTS]);
    }

    /** The rows no record of $class points to through $pointingTo (matching $criteria): NOT EXISTS. */
    public static function notExists(string $class, string $pointingTo, array $criteria = []): self
    {
        return new self(self::NOT_EXISTS, null, [$class, $pointingTo, $criteria], [self::NOT_EXISTS]);
    }

    /**
     * The rows whose $field is the greatest among the rows of their $per (the other criteria applied first): the
     * latest topic per category, `Criterion::greatestPer('lastPostAt', 'category')`.
     */
    public static function greatestPer(string $field, string $per): self
    {
        return new self(self::GREATEST_PER, null, [$field, $per], [self::GREATEST_PER]);
    }

    /**
     * The rows related to ALL these values through a to-many path: `Criterion::hasAll('taxa.slug', ['cm1', 'maths'])`.
     *
     * @param list<mixed> $values
     */
    public static function hasAll(string $path, array $values): self
    {
        return new self(self::HAS_ALL, null, [$path, array_values($values)], [self::HAS_ALL]);
    }

    /** Whether it is one of the conditions above, read by the repository before the persistence compiles the rest. */
    public function isRelational(): bool
    {
        return \in_array($this->field, [self::EXISTS, self::NOT_EXISTS, self::GREATEST_PER, self::HAS_ALL], true);
    }

    public const EXISTS = 'exists';
    public const NOT_EXISTS = 'notExists';
    public const GREATEST_PER = 'greatestPer';
    public const HAS_ALL = 'hasAll';

    /** @param list<string> $classes a class condition, named as the Parser names it in a finder (findByNotInstanceOf: "notInstanceOf") */
    private static function ofClass(string $word, array $classes): self
    {
        if ([] === $classes) {
            throw new \InvalidArgumentException(sprintf('%s needs a class.', $word));
        }

        return new self(lcfirst($word), null, 1 === \count($classes) ? $classes[0] : array_values($classes), [$word]);
    }

    public function is(string $option): bool
    {
        return \in_array($option, $this->options, true);
    }

    /**
     * Whether the criterion compares a to-one association by its key, without a join
     * (its value a Key, step E2b): a persistence compiles it on the row's own foreign key.
     */
    public function byKey(): bool
    {
        return $this->value instanceof Key;
    }

    /** The key the criterion has in the criteria array the compilers read ("slug:0", or the field itself). */
    public function key(): string
    {
        return null === $this->index ? $this->field : $this->field.Parser::SEPARATOR.$this->index;
    }
}
