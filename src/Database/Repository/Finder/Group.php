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
 *
 * A group can also be built from its members, without a name (step B1b): code
 * that holds fields at run time - the back office's search over the text
 * fields a list shows, its filters - says the same parentheses with criteria,
 * and a field holding a word of the language ("sortOrder", "colorAndSize")
 * stays a field:
 *
 *     Group::of(Parser::SEPARATOR_OR,
 *         Criterion::of('title', '%word%', Parser::OPTION_INSENSITIVE, Parser::OPTION_PARTIAL),
 *         Criterion::of('sortOrder', 3, Parser::OPTION_GREATER),
 *         Group::of(Parser::SEPARATOR_AND, Criterion::of('parent', Key::of(null)), Criterion::of('state', 'draft')),
 *     );
 *
 * It is the group already read: the Parser leaves it as it is, and both
 * compilers read its members as those of a named group.
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

    /**
     * A group built from its members (step B1b): criteria (Criterion::of()) and groups, joined by $separator. A
     * member's words are those a group's name may say - the comparisons, Partial, Insensitive, StartingWith and
     * EndingWith, Null, Empty, True, False and their Not..., the dates' words -, EqualTo when it says no comparison;
     * the members of a field are numbered in order ("id:0", "id:1"). An empty group adds no condition.
     *
     * @throws \InvalidArgumentException another separator, a word that is no condition, two comparisons on one member
     */
    public static function of(string $separator, Criterion|self ...$members): self
    {
        if (!\in_array($separator, [Parser::SEPARATOR_AND, Parser::SEPARATOR_OR], true)) {
            throw new \InvalidArgumentException(sprintf('A finder group joins its members with And or Or, not "%s".', $separator));
        }

        $criteria = $options = $arguments = $names = [];
        foreach ($members as $member) {
            if ($member instanceof self) {
                $arguments[] = $member->query ? $member : throw new \InvalidArgumentException(sprintf('The finder group "%s" was not read by a Parser.', $member->name));
                $names[] = $member->name;
                continue;
            }
            if ('' === $member->field || str_contains($member->field, Parser::SEPARATOR)) {
                throw new \InvalidArgumentException(sprintf('A finder group\'s member names a field or a path, not "%s".', $member->field));
            }
            $words = self::words($member);
            $index = 0;
            while (\array_key_exists($member->field.Parser::SEPARATOR.$index, $criteria)) {
                ++$index;
            }
            $key = $member->field.Parser::SEPARATOR.$index;
            $criteria[$key] = $arguments[$key] = $member->value;
            $options[$key] = $words;
            $names[] = $member->field.'('.implode(',', $words).')';
        }

        $query = new Query(
            method: '__'.Parser::REQUEST_FIND.Parser::SEPARATOR_BY,
            arguments: [$arguments],
            criteria: $criteria,
            options: $options,
            separator: $separator,
            column: null,
            cacheable: false,
            eagerly: false,
            request: Parser::REQUEST_FIND,
            special: null,
        );

        return new self('('.implode(' '.$separator.' ', $names).')', [], $query);
    }

    /** The modifiers of a member first, then its one comparison (EqualTo when it says none), as the Parser reads a name. */
    private static function words(Criterion $member): array
    {
        $modifiers = array_values(array_intersect([Parser::OPTION_PARTIAL, Parser::OPTION_INSENSITIVE], $member->options));
        $comparisons = array_values(array_diff($member->options, $modifiers));
        if ($refused = array_diff($comparisons, self::COMPARISONS)) {
            throw new \InvalidArgumentException(sprintf('A finder group holds conditions only: "%s" with %s.', $member->field, implode(', ', $refused)));
        }
        if (\count($comparisons) > 1) {
            throw new \InvalidArgumentException(sprintf('A finder group\'s member compares one way: "%s" with %s.', $member->field, implode(', ', $comparisons)));
        }

        return [...$modifiers, $comparisons[0] ?? Parser::OPTION_EQUAL];
    }

    /** The words that compare a member of a group built from its members (Group::of()). */
    private const COMPARISONS = [
        Parser::OPTION_EQUAL, Parser::OPTION_NOT_EQUAL,
        Parser::OPTION_GREATER, Parser::OPTION_GREATER_EQUAL, Parser::OPTION_LOWER, Parser::OPTION_LOWER_EQUAL,
        Parser::OPTION_STARTING_WITH, Parser::OPTION_ENDING_WITH, Parser::OPTION_NOT_STARTING_WITH, Parser::OPTION_NOT_ENDING_WITH,
        Parser::OPTION_NULL, Parser::OPTION_NOT_NULL, Parser::OPTION_EMPTY, Parser::OPTION_NOT_EMPTY,
        Parser::OPTION_TRUE, Parser::OPTION_NOT_TRUE, Parser::OPTION_FALSE, Parser::OPTION_NOT_FALSE,
        Parser::OPTION_WITHIN, Parser::OPTION_OLDER, Parser::OPTION_OLDER_EQUAL, Parser::OPTION_YOUNGER, Parser::OPTION_YOUNGER_EQUAL,
        Parser::OPTION_OVER, Parser::OPTION_NOT_OVER,
    ];

    /** Whether the group was read (by the Parser, or built from its members). */
    public function isRead(): bool
    {
        return null !== $this->query;
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
