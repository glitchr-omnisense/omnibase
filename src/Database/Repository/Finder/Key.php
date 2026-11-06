<?php

namespace Base\Database\Repository\Finder;

/**
 * A to-one association compared by its key, without a join (plan "omnibase
 * agnostic", step E2b): the value of a criterion, given in the criteria array
 * or to a name of the language.
 *
 *     $comments->findBy([
 *         'state' => CommentState::APPROVED,
 *         'thread' => Key::of($thread),   // thread_id = :id, or thread_id IS NULL for null
 *         'parent' => Key::of(null),
 *     ], ['createdAt' => 'DESC'], 200);
 *
 * A criterion on an association is otherwise compiled through a join of it
 * (`LEFT JOIN e.thread e_thread ... e_thread = :thread`), and a persistence
 * that scopes the joined entity there - Doctrine's trash filter on Thread, in
 * the join's ON - changes the rows: a comment whose thread is in the trash
 * reads as a comment "without a thread". A key criterion reads the row's own
 * foreign key: omnibridge/doctrine compiles it to `e.thread = :key`
 * (`IS NULL`, `IN`), what a hand-written `c.thread = :thread` said, and
 * omnibridge/eloquent to the foreign-key column.
 *
 * What a key criterion compares:
 * - an entity, its identifier, or null (no associated row);
 * - a list of them (IN); an empty list leaves the criterion out, as any list
 *   of the language does;
 * - with EqualTo (the default) or NotEqualTo only (NotEqualTo null: IS NOT
 *   NULL); any other word of the language is refused by the Parser;
 * - an association of the entity itself (not a path such as
 *   "thread.parent", which needs the join), single-valued: the compilers
 *   refuse the others.
 *
 * Not a word of the name: "Key" in a name would make every field holding the
 * word (Passkey's publicKeyCredentialId) a name the language refuses.
 */
final class Key
{
    /** The words a key criterion may carry: equality and its negation. */
    public const OPTIONS = [Parser::OPTION_EQUAL, Parser::OPTION_NOT_EQUAL];

    private function __construct(
        public readonly mixed $value,
    ) {
    }

    /**
     * @param object|int|string|list<object|int|string>|null $value an entity, its identifier, a list of them, or null
     */
    public static function of(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        foreach (\is_array($value) ? $value : [$value] as $member) {
            if (null !== $member && !\is_object($member) && !\is_int($member) && !\is_string($member)) {
                throw new \InvalidArgumentException(sprintf('A key criterion compares an entity, its identifier or null, not %s.', get_debug_type($member)));
            }
            if (\is_array($value) && null === $member) {
                throw new \InvalidArgumentException('A key criterion compares a list of entities or identifiers, without null: give null alone.');
            }
        }

        return new self(\is_array($value) ? array_values($value) : $value);
    }

    /** Whether the key compared is none (IS NULL, IS NOT NULL with NotEqualTo). */
    public function isNull(): bool
    {
        return null === $this->value;
    }

    /** Whether a list of keys is compared (IN, NOT IN with NotEqualTo). */
    public function isList(): bool
    {
        return \is_array($this->value);
    }
}
