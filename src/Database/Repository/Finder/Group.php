<?php

namespace Base\Database\Repository\Finder;

/**
 * A parenthesised part of a finder: a name of the language and its arguments,
 * given as one entry of the criteria array (plan "omnibase agnostic", step E2).
 *
 * A name joins its criteria with one separator, And or Or, never both: a query
 * such as `user = :user AND (title <> '' OR subject <> '')` had to be written
 * by hand. A group says the parentheses, in the language itself:
 *
 *     $notifications->count([
 *         'user' => $user,
 *         Group::by('TitleNotEmptyOrSubjectNotEmptyOrContentNotEmpty'),
 *     ]);
 *
 *     $translations->findBy([
 *         Group::by('InsensitivePartialTitleOrInsensitivePartialExcerpt', '%word%', '%word%'),
 *         'translatable.state' => ['publish'],
 *     ]);
 *
 * The name is read by the same Parser, as the criteria part of findBy<name>:
 * every word of a criterion is allowed (EqualTo, NotEmpty, Partial,
 * Insensitive, StartingWith, Null, YoungerThan...), and its own separator
 * joins its members. The group is then one criterion of the query, combined
 * with the others by the query's separator. Not allowed in a group: what is
 * no condition (ClosestTo and FarestTo order the rows, Model expands a
 * select), WithRoute, a criteria array or any argument after the named values.
 *
 * Parser::parse() hands back the group read ($query), which a persistence
 * compiles as it compiles a query's criteria: omnibridge/doctrine into a
 * parenthesised DQL expression, omnibridge/eloquent into a nested where.
 */
final class Group
{
    /**
     * @param list<mixed> $arguments the values of the name's criteria, in order
     */
    private function __construct(
        public readonly string $name,
        public readonly array $arguments,
        public readonly ?Query $query = null,
    ) {
    }

    public static function by(string $name, mixed ...$arguments): self
    {
        if ('' === $name) {
            throw new \InvalidArgumentException('A finder group needs a name of the language ("TitleNotEmptyOrSubjectNotEmpty").');
        }

        return new self(ucfirst($name), array_values($arguments));
    }

    /** The same group, read by the parser: findBy<name> with its arguments. */
    public function read(Query $query): self
    {
        return new self($this->name, $this->arguments, $query);
    }

    /** How the members join: And or Or (Parser::SEPARATOR_*). */
    public function separator(): string
    {
        return $this->parsed()->separator ?? Parser::SEPARATOR_AND;
    }

    /**
     * The members, each with its words, as a query's criterions.
     *
     * @return list<Criterion>
     */
    public function criterions(): array
    {
        return $this->parsed()->criterions();
    }

    private function parsed(): Query
    {
        return $this->query ?? throw new \LogicException(sprintf('The finder group "%s" was not read by a Parser.', $this->name));
    }
}
