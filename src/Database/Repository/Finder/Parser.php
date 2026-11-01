<?php

namespace Base\Database\Repository\Finder;

use Exception;

/**
 * The finder language of omnibase - findByEmailAndUsername(), findOneBySlug(),
 * cacheOneByStateAndSlugStartingWith(), countByStateAndPublishedAtYoungerThan(),
 * findPreviousOneByClassOfAndState(), lengthOfTagsBy()... - read from a method's
 * name and its arguments into a Query: the request, the internal method it leads
 * to, its arguments, the criteria the name declares with their options (EqualTo,
 * NotEqualTo, GreaterThan, LowerThan, GreaterEqualTo, LowerEqualTo, YoungerThan,
 * OlderThan, Within, IsOver, StartingWith, EndingWith, Null, Empty, True, False
 * and their Not..., Insensitive, Partial, ClosestTo, FarestTo, InstanceOf,
 * ClassOf, MemberOf, But, WithRoute, Model), the separator (And, Or), the column
 * (countFor...By, lengthOf...By), the specials (One, All, Randomly, AtMost<N>,
 * Last<N>, LastOne, Previous<N>, PreviousOne, Next<N>, NextOne), cache and
 * Eagerly.
 *
 * Nothing of an ORM: what it asks of the entity is a Subject (its class, its
 * fields, a Query-by-Example's criteria), and a host's request (WithRoute) is
 * turned into route parameters by the resolver it is given. A persistence
 * compiles the Query into its own query language - Doctrine's DQL in
 * ServiceEntityParser (omnibridge/doctrine at step F3), Eloquent's builder in
 * omnibridge/eloquent (F7) - so the language is one, whatever the backend
 * (plan "omnibase agnostic", 2026-11-01, step F1: Marco's invention, kept whole
 * in the core). The parse is the one ServiceEntityParser::__parse() made before:
 * tests/Database/Repository/FinderParityTest compares it, word for word.
 */
final class Parser
{
    public const REQUEST_FIND = "find";
    public const REQUEST_CACHE = "cache";
    public const REQUEST_COUNT = "count";
    public const REQUEST_DISTINCT = "distinctCount";
    public const REQUEST_LENGTH = "lengthOf";

    public const LOAD_EAGERLY = "Eagerly";

    public const SPECIAL_ALL = "All";
    public const SPECIAL_ONE = "One";
    public const SPECIAL_RAND = "Randomly";
    public const SPECIAL_ATMOST = "AtMost(?P<atMost>[0-9])*";
    public const SPECIAL_LASTONE = "LastOne";
    public const SPECIAL_LAST = "Last(?P<last>[0-9])*";
    public const SPECIAL_PREVONE = "PreviousOne";
    public const SPECIAL_PREV = "Previous(?P<prev>[0-9]*)";
    public const SPECIAL_NEXTONE = "NextOne";
    public const SPECIAL_NEXT = "Next(?P<next>[0-9]*)";

    public static function getSpecial(string $special): string
    {
        return only_alphanumerics(trim_brackets($special));
    }

    public static function getSpecials(): array
    {
        return [self::SPECIAL_ALL, self::SPECIAL_ONE, self::SPECIAL_ATMOST, self::SPECIAL_LAST, self::SPECIAL_LASTONE,
            self::SPECIAL_RAND, self::SPECIAL_PREVONE, self::SPECIAL_NEXTONE, self::SPECIAL_PREV, self::SPECIAL_NEXT];
    }

    // Default options without argument
    public const OPTION_EMPTY = "Empty";
    public const OPTION_NOT_EMPTY = "NotEmpty";
    public const OPTION_TRUE = "True";
    public const OPTION_NOT_TRUE = "NotTrue";
    public const OPTION_FALSE = "False";
    public const OPTION_NOT_FALSE = "NotFalse";
    public const OPTION_NULL = "Null";
    public const OPTION_NOT_NULL = "NotNull";

    // Default options
    public const OPTION_EQUAL = "EqualTo";
    public const OPTION_NOT_EQUAL = "NotEqualTo";
    public const OPTION_GREATER = "GreaterThan";
    public const OPTION_GREATER_EQUAL = "GreaterEqualTo";
    public const OPTION_LOWER = "LowerThan";
    public const OPTION_LOWER_EQUAL = "LowerEqualTo";

    // Datetime related options
    public const OPTION_WITHIN = "Within";
    public const OPTION_OLDER = "OlderThan";
    public const OPTION_OLDER_EQUAL = "OlderEqualTo";
    public const OPTION_YOUNGER = "YoungerThan";
    public const OPTION_YOUNGER_EQUAL = "YoungerEqualTo";
    public const OPTION_OVER = "IsOver";
    public const OPTION_NOT_OVER = "IsNotOver";

    // String related options
    public const OPTION_STARTING_WITH = "StartingWith";
    public const OPTION_ENDING_WITH = "EndingWith";
    public const OPTION_NOT_STARTING_WITH = "NotStartingWith";
    public const OPTION_NOT_ENDING_WITH = "NotEndingWith";

    // Custom options
    public const OPTION_INSENSITIVE = "Insensitive";
    public const OPTION_WITH_ROUTE = "WithRoute";
    public const OPTION_PARTIAL = "Partial";
    public const OPTION_MODEL = "Model";
    public const OPTION_CLASSOF = "ClassOf";
    public const OPTION_NOT_CLASSOF = "NotClassOf";
    public const OPTION_INSTANCEOF = "InstanceOf";
    public const OPTION_NOT_INSTANCEOF = "NotInstanceOf";
    public const OPTION_MEMBEROF = "MemberOf";
    public const OPTION_NOT_MEMBEROF = "NotMemberOf";
    public const OPTION_BUT = "But";

    public const OPTION_CLOSESTTO = "ClosestTo";
    public const OPTION_FARESTTO = "FarestTo";

    // Separators
    public const SEPARATOR = ":"; // Field separator
    public const SEPARATOR_OR = "Or";
    public const SEPARATOR_AND = "And";
    public const SEPARATOR_FOR = "For";
    public const SEPARATOR_BY = "By";

    // Count options
    public const COUNT_ALL = "ALL";
    public const COUNT_DISTINCT = "DISTINCT";

    protected $operator = null;
    protected mixed $column = null;
    protected bool $cacheable = false;
    protected array|bool $eagerly = false;
    protected array $criteria = [];
    protected array $options = [];
    protected string $request = self::REQUEST_FIND;
    protected ?string $special = null;

    /**
     * @param Subject       $subject        the entity the finder is asked of
     * @param \Closure|null $routeParameters a host's request or event (WithRoute's first argument when it is no array) => its route parameters (an array or null), false for anything else
     */
    public function __construct(
        private readonly Subject $subject,
        private readonly ?\Closure $routeParameters = null,
    ) {
    }

    /**
     * Reads a finder's name and arguments.
     *
     * @param list<mixed> $arguments
     *
     * @throws Exception an unknown request, an ambiguous name (And with Or), a field whose name holds a word of the language, a missing or wrong argument
     */
    public function parse(string $method, array $arguments): Query
    {
        $this->criteria = [];
        $this->options = [];
        $this->operator = null;
        $this->column = null;
        $this->cacheable = false;
        $this->eagerly = false;
        $this->request = self::REQUEST_FIND;
        $this->special = null;

        [$internal, $internalArguments] = $this->__parse($method, $arguments);

        return new Query(
            method: $internal,
            arguments: $internalArguments,
            criteria: $this->criteria,
            options: $this->options,
            separator: $this->operator,
            column: $this->column,
            cacheable: $this->cacheable,
            eagerly: $this->eagerly,
            request: $this->request,
            special: $this->special,
        );
    }

    /**
     * @param string|null $by
     * @param $value
     * @return string
     * @throws Exception
     */
    protected function addCriteria(?string $by, $value)
    {
        if ($by != null && empty($by)) {
            throw new Exception("Tried to add unnamed criteria");
        }

        $index = 0;
        while (array_key_exists($by . self::SEPARATOR . $index, $this->criteria)) {
            $index++;
        }

        $this->criteria[$by . self::SEPARATOR . $index] = $value;
        return $by . self::SEPARATOR . $index;
    }

    /**
     * @return mixed|null
     */
    protected function getColumn()
    {
        return $this->column;
    }

    /**
     * @param string $column
     * @return $this
     */
    protected function setColumn(string $column)
    {
        $this->column = $column;
        return $this;
    }

    /**
     * @return mixed|null
     */
    protected function getSeparator()
    {
        return $this->operator;
    }

    /**
     * @param $operator
     * @return $this
     */
    protected function setSeparator($operator)
    {
        $this->operator = $operator;
        return $this;
    }

    /**
     * @param $method
     * @param $by
     * @param $option
     * @return array
     */
    protected function stripByFront($method, $by, $option)
    {
        $method = str_starts_with($method, $option) ? substr($method, strlen($option), strlen($method)) : $method;
        $by = str_starts_with($by, $option) ? substr($by, strlen($option), strlen($by)) : $by;

        return [$method, $by];
    }

    /**
     * @param $method
     * @param $by
     * @param $option
     * @return array
     */
    protected function stripByEnd($method, $by, $option)
    {
        $method = str_ends_with($method, $option) ? substr($method, 0, strpos($method, $option)) : $method;
        $by = str_ends_with($by, $option) ? substr($by, 0, strlen($by) - strlen($option)) : $by;

        return [$method, $by];
    }

    /**
     * @param string $id
     * @return array|mixed
     */
    public function getCustomOption(string $id)
    {
        return $this->options[$id] ?? [];
    }

    /**
     * @param string $id
     * @param string $option
     * @return bool
     */
    public function findCustomOption(string $id, string $option)
    {
        return in_array($option, $this->getCustomOption($id));
    }

    protected function addCustomOption(string $id, string $option)
    {
        if (!array_key_exists($id, $this->criteria)) {
            throw new Exception("Criteria ID \"$id\" not found in criteria list.. wrong usage? use \"addCriteria\" first.");
        }

        if (!array_key_exists($id, $this->options)) {
            $this->options[$id] = [];
        }

        $this->options[$id][] = $option;
    }

    /**
     * A bare clause is a Query-by-Example select clause when it names the generic
     * "Model" keyword OR the entity's own / an ancestor's short class name
     * (findByModel / findBySubscriber / findByThread). A real field or association
     * of the entity always takes precedence.
     */
    protected function isSelectClause(string $by): bool
    {
        $name = lcfirst($by);
        if ($this->subject->hasField($name)) {
            return false;
        }

        if ($by === self::OPTION_MODEL) {
            return true;
        }

        $entity = $this->subject->getClassName();
        foreach (array_merge([$entity], array_values(class_parents($entity) ?: [])) as $class) {
            $short = ($pos = strrpos($class, "\\")) !== false ? substr($class, $pos + 1) : $class;
            if ($by === $short) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $method
     * @param $arguments
     * @return array
     * @throws MappingException
     */
    protected function __parse($method, $arguments)
    {
        $method = str_strip($method, "__");
        $method = explode("::", $method)[1] ?? $method;

        // Head parameters (depends on the method name) + default ones
        // Default parameters:
        // - "MAGIC ARGUMENTS"
        // - "MAGIC ARGUMENTS"+0: criteria
        // - "MAGIC ARGUMENTS"+1: orderBy
        // - "MAGIC ARGUMENTS"+2: limit
        // - "MAGIC ARGUMENTS"+3: offset
        // - "MAGIC ARGUMENTS"+4: groupBy
        // - "MAGIC ARGUMENTS"+5: selectAs

        // Definition of the returned values
        $magicFn = null;
        $magicArgs = [];

        foreach ($this->subject->getFieldNames() as $field) {
            $conflictingOption = null;
            $optionsToCheck = [
                self::OPTION_WITH_ROUTE,
                self::OPTION_BUT,
                self::OPTION_MODEL,
                self::OPTION_INSTANCEOF,
                self::OPTION_NOT_INSTANCEOF,
                self::OPTION_CLASSOF,
                self::OPTION_NOT_CLASSOF,
                self::OPTION_MEMBEROF,
                self::OPTION_NOT_MEMBEROF,
                self::OPTION_PARTIAL,
                self::OPTION_INSENSITIVE,
                self::OPTION_CLOSESTTO,
                self::OPTION_FARESTTO,
                self::OPTION_STARTING_WITH,
                self::OPTION_ENDING_WITH,
                self::OPTION_NOT_STARTING_WITH,
                self::OPTION_NOT_ENDING_WITH,
                self::OPTION_LOWER,
                self::OPTION_GREATER,
                self::OPTION_LOWER_EQUAL,
                self::OPTION_GREATER_EQUAL,
                self::OPTION_OVER,
                self::OPTION_NOT_OVER,
                self::OPTION_YOUNGER,
                self::OPTION_OLDER,
                self::OPTION_WITHIN,
                self::OPTION_YOUNGER_EQUAL,
                self::OPTION_OLDER_EQUAL,
                self::OPTION_EQUAL,
                self::OPTION_NOT_EQUAL,
                self::OPTION_NULL,
                self::OPTION_NOT_NULL,
                self::OPTION_EMPTY,
                self::OPTION_NOT_EMPTY,
                self::OPTION_TRUE,
                self::OPTION_NOT_TRUE,
                self::OPTION_FALSE,
                self::OPTION_NOT_FALSE,
                self::SEPARATOR_AND,
                self::SEPARATOR_OR,
                self::SEPARATOR
            ];
            foreach ($optionsToCheck as $option) {
                $pos = strpos($field, $option);
                if ($pos !== false) {
                    $nextChar = substr($field, $pos + strlen($option), 1);
                    if ($nextChar !== '' && ctype_upper($nextChar)) {
                        $conflictingOption = $option;
                        break;
                    }
                }
            }
            if ($conflictingOption !== null) {
                throw new Exception(
                    "\"" . $this->subject->getClassName() . "\" entity has a field called \"$field\". " .
                    "This is unfortunate, because this word (\"$conflictingOption\") is used to customize DQL queries. " .
                    "Please build your own DQL query or change your database field name"
                );
            }
        }

        // Find and sort the "With" list
        $withs = array_filter([
            self::OPTION_WITH_ROUTE => strpos($method, self::OPTION_WITH_ROUTE)
            /* ... more type of options here */
        ], fn($value) => ($value !== false));
        asort($withs);


        // Here are the resulting parameters
        $routeParameters = null;
        foreach (array_keys($withs) as $with) {
            switch ($with) {
                // If variable with route parameter found.
                // Extract request from the provided arguments
                case self::OPTION_WITH_ROUTE:

                    $arrayOrEventOrRequest = array_shift($arguments);
                    if (!is_array($arrayOrEventOrRequest)) {
                        // A host's request or event (Symfony's Request or KernelEvent, through the
                        // repository's resolver): its route parameters; false when it is neither.
                        $resolved = null === $this->routeParameters ? false : ($this->routeParameters)($arrayOrEventOrRequest);
                        if (false === $resolved) {
                            throw new Exception("At least one parameter requires route parameter in your method call. First parameter must be either an instance of 'Request', 'KernelEvent' or 'array'");
                        }

                        $arrayOrEventOrRequest = $resolved;
                    }

                    $routeParameters = $arrayOrEventOrRequest;
                    break;

                default:
                    throw new Exception("Unexpected \"Entity\" proposition found: \"" . $with . "\"");
            }
        }

        // Extract method name and extra parameters
        $findRequest = self::REQUEST_CACHE . "|" .
            self::REQUEST_FIND;

        $countRequest = self::REQUEST_DISTINCT . "|" .
            self::REQUEST_COUNT . "|" .
            self::REQUEST_CACHE . ucfirst(self::REQUEST_DISTINCT) . "|" .
            self::REQUEST_CACHE . ucfirst(self::REQUEST_COUNT);

        $lengthRequest = self::REQUEST_LENGTH;
        $specials = implode("|", self::getSpecials());

        $eagerly = self::LOAD_EAGERLY;
        $by = self::SEPARATOR_BY;
        $for = self::SEPARATOR_FOR;

        $special = null;
        $magicExtra = [];

        $requestType = self::REQUEST_FIND;
        if (preg_match('/^(?P<fn>' . $countRequest . ')(?:' . $for . '(?P<column>[^' . $by . ']*))?' . $by . '(?P<names>.*)/', $method, $magicExtra)) {
            $requestType = self::REQUEST_COUNT;
            $byNames = array_pop_key("names", $magicExtra);
            $this->setColumn(lcfirst(array_pop_key("column", $magicExtra)) ?? null);

            $magicFn = only_alphanumerics(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));
        } elseif (preg_match('/^(?P<fn>' . $countRequest . ')(?:' . $for . '(?P<column>[^' . $by . ']*))?(?P<names>.*)/', $method, $magicExtra)) {
            $requestType = self::REQUEST_COUNT;
            $byNames = array_pop_key("names", $magicExtra);
            $this->setColumn(lcfirst(array_pop_key("column", $magicExtra)) ?? null);

            $magicFn = only_alphanumerics(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));
        } elseif (preg_match('/^(?P<fn>' . $lengthRequest . ')(?P<column>[^' . $by . ']+)' . $by . '(?P<names>.*)/', $method, $magicExtra)) {
            $requestType = self::REQUEST_LENGTH;
            $byNames = array_pop_key("names", $magicExtra);
            $this->setColumn(lcfirst(array_pop_key("column", $magicExtra)) ?? null);

            $magicFn = only_alphanumerics(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));
        } elseif (preg_match('/^(?P<fn>' . $lengthRequest . ')(?P<column>[^' . $by . ']+)(?P<names>.*)/', $method, $magicExtra)) {
            $requestType = self::REQUEST_LENGTH;
            $byNames = array_pop_key("names", $magicExtra);

            $magicFn = only_alphanumerics(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));

            $this->setColumn(lcfirst(array_pop_key("column", $magicExtra)) ?? null);
        } elseif (preg_match('/^(?P<fn>(?:' . $findRequest . ')(?P<special>' . $specials . ')?(?P<eagerly>' . $eagerly . ')?' . $by . ')(?P<names>.*)/', $method, $magicExtra)) {
            $this->eagerly = !empty(array_pop_key("eagerly", $magicExtra));
            $byNames = array_pop_key("names", $magicExtra);
            $special = array_pop_key("special", $magicExtra);
            $special = $special ? only_alphachars(ucfirst($special)) : null;

            $magicFn = only_alphachars(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));
        } elseif (preg_match('/^(?P<fn>(?:' . $findRequest . ')(?P<special>' . $specials . ')?(?P<eagerly>' . $eagerly . ')?)(?P<names>.*)/', $method, $magicExtra)) {
            $this->eagerly = !empty(array_pop_key("eagerly", $magicExtra));
            $byNames = array_pop_key("names", $magicExtra);
            $special = array_pop_key("special", $magicExtra);
            $special = $special ? only_alphachars(ucfirst($special)) : null;

            $magicFn = only_alphachars(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));
        } else {
            throw new Exception(sprintf(
                'Undefined method "%s". The method name must start with ' .
                'either cache[PreviousOne|NextOne|One|Randomly|AtMost|Next|Previous|Last|LastOne|All][By], find[PreviousOne|NextOne|One|Randomly|AtMost|Next|Previous|Last|LastOne][By], distinctCount, count, lengthOf!',
                $method
            ));
        }

        // Handle special cases
        if (in_array($special, [self::getSpecial(self::SPECIAL_NEXTONE), self::getSpecial(self::SPECIAL_NEXT)])) {
            
            $specialArgs = array_shift($arguments);
            if(is_int($specialArgs)) $specialArgs = ["id" => $specialArgs];
            if(!is_array($specialArgs)) {
                throw new Exception("Warning /!\ Array expected for getting result on `findNext|findNextOne` special instruction");
            }

            $magicExtra["nextKeys"] = array_keys($specialArgs ?? []);
            foreach($specialArgs as $_fieldName => $_arg) {
                $this->addCustomOption($this->addCriteria($_fieldName, $_arg), self::OPTION_GREATER);
            }

        } elseif (in_array($special, [self::getSpecial(self::SPECIAL_PREVONE), self::getSpecial(self::SPECIAL_PREV)])) {

            $specialArgs = array_shift($arguments);
            if(is_int($specialArgs)) $specialArgs = ["id" => $specialArgs];
            if(!is_array($specialArgs)) {
                throw new Exception("Warning /!\ Array expected for getting result on `findPrevious|findPreviousOne` special instruction");
            }

            $magicExtra["prevKeys"] = array_keys($specialArgs);
            foreach($specialArgs as $_fieldName => $_arg) {
                $this->addCustomOption($this->addCriteria($_fieldName, $_arg), self::OPTION_LOWER);
            }
        }

        if ($this->eagerly !== false) {
            $magicFn = str_replace(self::LOAD_EAGERLY, "", $magicFn);
            $this->eagerly = array_shift($arguments);
            if (!is_array($this->eagerly)) {
                throw new Exception("Warning /!\ Eager queries requires an array as first argument");
            }
        }

        // Reveal obvious logical ambiguities..
        $byNames = str_starts_with($byNames, self::SEPARATOR_BY) ? substr($byNames, strlen(self::SEPARATOR_BY)) : $byNames;
        if (str_contains($byNames, self::SEPARATOR_AND) && str_contains($byNames, self::SEPARATOR_OR)) {
            throw new Exception("\"" . $byNames . "\" method gets an AND/OR ambiguity");
        }

        // Desintangle logical operators
        if (str_contains($byNames, self::SEPARATOR_OR)) {
            $this->setSeparator(self::SEPARATOR_OR);
            $byNames = explode(self::SEPARATOR_OR, $byNames);
        } else {
            $this->setSeparator(self::SEPARATOR_AND);
            $byNames = explode(self::SEPARATOR_AND, $byNames);
        }

        $methodBak = $method;
        $method = str_starts_with($method, $magicFn) ? substr($method, strlen($magicFn)) : $method;

        $operator = self::OPTION_EQUAL; // Default case (e.g. "findBy" alone)
        foreach ($byNames as $id => $by) {
            $oldBy = false;

            $isModel = false;
            $isInsensitive = $isPartial = false;
            $withRoute = false;
            $but = false;

            $closestTo = $farestTo = false;
            $instanceOf = $notInstanceOf = false;
            $classOf = $notClassOf = false;
            $memberOf = $notMemberOf = false;

            $operator = self::OPTION_EQUAL;
            while ($oldBy != $by) {
                $oldBy = $by;

                $option = null;
                if (str_starts_with($by, self::OPTION_PARTIAL)) {
                    $option = self::OPTION_PARTIAL;
                } elseif (str_starts_with($by, self::OPTION_INSENSITIVE)) {
                    $option = self::OPTION_INSENSITIVE;
                } elseif (str_ends_with($by, self::OPTION_WITH_ROUTE)) {
                    $option = self::OPTION_WITH_ROUTE;
                } elseif (str_ends_with($by, self::OPTION_CLOSESTTO)) {
                    $option = self::OPTION_CLOSESTTO;
                } elseif (str_ends_with($by, self::OPTION_FARESTTO)) {
                    $option = self::OPTION_FARESTTO;
                } elseif (str_ends_with($by, self::OPTION_BUT)) {
                    $option = self::OPTION_BUT;
                } elseif (str_ends_with($by, self::OPTION_NOT_INSTANCEOF)) { // a negative word before its positive one, which it ends with
                    $option = self::OPTION_NOT_INSTANCEOF;
                } elseif (str_ends_with($by, self::OPTION_INSTANCEOF)) {
                    $option = self::OPTION_INSTANCEOF;
                } elseif (str_ends_with($by, self::OPTION_NOT_CLASSOF)) {
                    $option = self::OPTION_NOT_CLASSOF;
                } elseif (str_ends_with($by, self::OPTION_CLASSOF)) {
                    $option = self::OPTION_CLASSOF;
                } elseif (str_ends_with($by, self::OPTION_NOT_MEMBEROF)) {
                    $option = self::OPTION_NOT_MEMBEROF;
                } elseif (str_ends_with($by, self::OPTION_MEMBEROF)) {
                    $option = self::OPTION_MEMBEROF;
                } elseif ($this->isSelectClause($by)) {
                    $option = self::OPTION_MODEL;
                // A negative word before its positive one, which it ends with ("NotStartingWith").
                } elseif (str_ends_with($by, self::OPTION_NOT_STARTING_WITH)) {
                    $option = self::OPTION_NOT_STARTING_WITH;
                } elseif (str_ends_with($by, self::OPTION_NOT_ENDING_WITH)) {
                    $option = self::OPTION_NOT_ENDING_WITH;
                } elseif (str_ends_with($by, self::OPTION_STARTING_WITH)) {
                    $option = self::OPTION_STARTING_WITH;
                } elseif (str_ends_with($by, self::OPTION_ENDING_WITH)) {
                    $option = self::OPTION_ENDING_WITH;
                } elseif (str_ends_with($by, self::OPTION_OVER)) {
                    $option = self::OPTION_OVER;
                } elseif (str_ends_with($by, self::OPTION_NOT_OVER)) {
                    $option = self::OPTION_NOT_OVER;
                } elseif (str_ends_with($by, self::OPTION_YOUNGER)) {
                    $option = self::OPTION_YOUNGER;
                } elseif (str_ends_with($by, self::OPTION_YOUNGER_EQUAL)) {
                    $option = self::OPTION_YOUNGER_EQUAL;
                } elseif (str_ends_with($by, self::OPTION_WITHIN)) {
                    $option = self::OPTION_WITHIN;
                } elseif (str_ends_with($by, self::OPTION_OLDER)) {
                    $option = self::OPTION_OLDER;
                } elseif (str_ends_with($by, self::OPTION_OLDER_EQUAL)) {
                    $option = self::OPTION_OLDER_EQUAL;
                } elseif (str_ends_with($by, self::OPTION_LOWER_EQUAL)) {
                    $option = self::OPTION_LOWER_EQUAL;
                } elseif (str_ends_with($by, self::OPTION_LOWER)) {
                    $option = self::OPTION_LOWER;
                } elseif (str_ends_with($by, self::OPTION_GREATER_EQUAL)) {
                    $option = self::OPTION_GREATER_EQUAL;
                } elseif (str_ends_with($by, self::OPTION_GREATER)) {
                    $option = self::OPTION_GREATER;
                } elseif (str_ends_with($by, self::OPTION_NOT_EQUAL)) {
                    $option = self::OPTION_NOT_EQUAL;
                } elseif (str_ends_with($by, self::OPTION_EQUAL)) {
                    $option = self::OPTION_EQUAL;
                } elseif (str_ends_with($by, self::OPTION_NOT_NULL)) {
                    $option = self::OPTION_NOT_NULL;
                } elseif (str_ends_with($by, self::OPTION_NULL)) {
                    $option = self::OPTION_NULL;
                } elseif (str_ends_with($by, self::OPTION_NOT_EMPTY)) {
                    $option = self::OPTION_NOT_EMPTY;
                } elseif (str_ends_with($by, self::OPTION_EMPTY)) {
                    $option = self::OPTION_EMPTY;
                } elseif (str_ends_with($by, self::OPTION_NOT_TRUE)) {
                    $option = self::OPTION_NOT_TRUE;
                } elseif (str_ends_with($by, self::OPTION_TRUE)) {
                    $option = self::OPTION_TRUE;
                } elseif (str_ends_with($by, self::OPTION_NOT_FALSE)) {
                    $option = self::OPTION_NOT_FALSE;
                } elseif (str_ends_with($by, self::OPTION_FALSE)) {
                    $option = self::OPTION_FALSE;
                }

                switch ($option) {
                    case self::OPTION_PARTIAL:
                        $isPartial = true;
                        list($method, $by) = $this->stripByFront($method, $by, $option);
                        break;

                    case self::OPTION_INSENSITIVE:
                        $isInsensitive = true;
                        list($method, $by) = $this->stripByFront($method, $by, $option);
                        break;

                    case self::OPTION_WITH_ROUTE:
                        $withRoute = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_BUT:
                        $but = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_CLOSESTTO:
                        $closestTo = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;
                    case self::OPTION_FARESTTO:
                        $farestTo = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_INSTANCEOF:
                        $instanceOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_NOT_INSTANCEOF:
                        $notInstanceOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_CLASSOF:
                        $classOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_NOT_CLASSOF:
                        $notClassOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_MEMBEROF:
                        $memberOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_NOT_MEMBEROF:
                        $notMemberOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_MODEL:
                        $isModel = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    // String related
                    case self::OPTION_STARTING_WITH:
                    case self::OPTION_ENDING_WITH:
                    case self::OPTION_NOT_STARTING_WITH:
                    case self::OPTION_NOT_ENDING_WITH:

                        // Datetime related
                    case self::OPTION_YOUNGER:
                    case self::OPTION_YOUNGER_EQUAL:
                    case self::OPTION_OLDER:
                    case self::OPTION_OLDER_EQUAL:
                    case self::OPTION_WITHIN:
                    case self::OPTION_OVER:
                    case self::OPTION_NOT_OVER:

                        // Number related
                    case self::OPTION_LOWER:
                    case self::OPTION_LOWER_EQUAL:
                    case self::OPTION_GREATER:
                    case self::OPTION_GREATER_EQUAL:
                    case self::OPTION_EQUAL:
                    case self::OPTION_NOT_EQUAL:
                    case self::OPTION_EMPTY:
                    case self::OPTION_NOT_EMPTY:
                    case self::OPTION_TRUE:
                    case self::OPTION_NOT_TRUE:
                    case self::OPTION_FALSE:
                    case self::OPTION_NOT_FALSE:
                    case self::OPTION_NULL:
                    case self::OPTION_NOT_NULL:
                        $operator = $option;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;
                }
            }

            // First check if WithRoute special argument is found..
            // This argument will retrieve the value of the corresponding route parameter
            // and use it in the query
            if ($withRoute) {
                $key = array_shift($arguments);

                // Stop dev using partial information when using route parameter
                // Doing so.. user would be able to inject LIKE commands directly from URL..
                if ($isPartial) {
                    throw new Exception("Partial field \"$by\" using route parameter is not implemented. Consider removing \"Partial\" prefix from \"$methodBak\"");
                }

                // Process self::OPTION_WITH_ROUTE method
                if ($method == self::OPTION_WITH_ROUTE) {
                    throw new Exception("Missing parameter to associate with operator 'withRouteParameter'");
                }

                $fieldValue = $routeParameters[$key] ?? null;
                if (!empty($fieldValue)) {
                    // Check if partial match is enabled
                    $by = lcfirst($by);

                    $id = $this->addCriteria($by, $fieldValue);
                    if ($operator == self::OPTION_EQUAL) {
                        $this->addCustomOption($id, $operator);
                    } else {
                        throw new Exception("Unexpected operator \"" . $operator . "\" found in model definition");
                    }
                }
            } elseif ($but) {
                $fieldValue = array_shift($arguments);
                if (!empty($fieldValue)) {
                    $by = "id";
                    $operator = self::OPTION_NOT_EQUAL;

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, $operator);
                }
            } elseif ($instanceOf) {
                $fieldValue = array_shift($arguments);
                if (!empty($fieldValue)) {
                    $by = lcfirst(self::OPTION_INSTANCEOF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_INSTANCEOF);
                }
            } elseif ($notInstanceOf) {
                $fieldValue = array_shift($arguments);
                if (!empty($fieldValue)) {
                    $by = lcfirst(self::OPTION_NOT_INSTANCEOF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_NOT_INSTANCEOF);
                }
            } elseif ($classOf) {
                $fieldValue = array_shift($arguments);
                if (!empty($fieldValue)) {
                    $by = lcfirst(self::OPTION_CLASSOF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_CLASSOF);
                }
            } elseif ($notClassOf) {
                $fieldValue = array_shift($arguments);
                if (!empty($fieldValue)) {
                    $by = lcfirst(self::OPTION_NOT_CLASSOF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_NOT_CLASSOF);
                }
            } elseif ($memberOf) {
                $fieldValue = array_shift($arguments);
                if (!empty($fieldValue)) {
                    $by = lcfirst(self::OPTION_MEMBEROF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_MEMBEROF);
                }
            } elseif ($notMemberOf) {
                $fieldValue = array_shift($arguments);
                if (!empty($fieldValue)) {
                    $by = lcfirst(self::OPTION_NOT_MEMBEROF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_NOT_MEMBEROF);
                }
            } elseif ($isModel) {
                // Query-by-Example clause: consume a select entity and expand its
                // set fields into criteria (AND-combined at compile time). Composes
                // with "And" only — "Or" would need parentheses this DSL forbids.
                if ($this->getSeparator() === self::SEPARATOR_OR) {
                    throw new Exception("\"Model\" cannot be combined with \"Or\" (would need parentheses); use \"And\" only.");
                }

                $select = array_shift($arguments);
                if (!is_object($select)) {
                    throw new Exception("\"Model\" expects a select object argument, got \"" . gettype($select) . "\".");
                }

                $modelCriteria = $this->subject->criteriaFromSelect($select);
                if (empty($modelCriteria)) {
                    throw new Exception("\"Model\" select (" . get_class($select) . ") has no queryable set fields for \"" . $this->subject->getClassName() . "\".");
                }

                $id = $this->addCriteria(lcfirst(self::OPTION_MODEL), $modelCriteria);
                if ($isPartial) {         // findByPartialModel -> LIKE per select field
                    $this->addCustomOption($id, self::OPTION_PARTIAL);
                }
                if ($isInsensitive) {     // findByInsensitiveModel -> LOWER() per select field
                    $this->addCustomOption($id, self::OPTION_INSENSITIVE);
                }
            } elseif ($by) {
                $by = lcfirst($by);

                $fieldDate = ($operator == self::OPTION_OVER || $operator == self::OPTION_NOT_OVER);
                $fieldNull = ($operator == self::OPTION_NULL || $operator == self::OPTION_NOT_NULL);
                $fieldEmpty = ($operator == self::OPTION_EMPTY || $operator == self::OPTION_NOT_EMPTY);
                $fieldBool = ($operator == self::OPTION_TRUE || $operator == self::OPTION_NOT_TRUE ||
                    $operator == self::OPTION_FALSE || $operator == self::OPTION_NOT_FALSE);

                if ($fieldEmpty) {
                    $fieldValue = "";
                } elseif ($fieldNull) {
                    $fieldValue = null;
                } elseif ($fieldBool) {
                    $fieldValue = true;
                } elseif ($fieldDate) {
                    $fieldValue = "CURDATE()";
                } else {
                    $fieldValue = array_shift($arguments);
                }

                $id = $this->addCriteria($by, $fieldValue);

                if ($isPartial) {
                    $this->addCustomOption($id, self::OPTION_PARTIAL);
                }
                if ($isInsensitive) {
                    $this->addCustomOption($id, self::OPTION_INSENSITIVE);
                }
                if ($closestTo) {
                    $this->addCustomOption($id, self::OPTION_CLOSESTTO);
                }
                if ($farestTo) {
                    $this->addCustomOption($id, self::OPTION_FARESTTO);
                }
                if ($operator) {
                    $this->addCustomOption($id, $operator);
                }
            }
        }

        // Index definition:
        // "criteria"  = argument #0, after removal of head parameters
        foreach ($arguments as $i => $arg) {
            $magicArgs[$i] = $arg;
        } // +1 because of array shift

        //
        // Mark as cacheable (to be used in self::getQueryBuilder)
        if (str_starts_with($magicFn, self::REQUEST_CACHE)) {
            $magicFn = lcfirst(str_lstrip($magicFn, self::REQUEST_CACHE));
            if ($requestType == self::REQUEST_FIND && !str_starts_with($magicFn, self::REQUEST_FIND)) {
                $magicFn = self::REQUEST_FIND . ucfirst($magicFn);
            } // find <-> cache, findOne <-> cacheOne, [...]

            $this->cacheable = !is_cli();
        }

        if (str_starts_with($magicFn, self::REQUEST_FIND . self::SPECIAL_ALL)) {
            if (str_ends_with($magicFn, self::SEPARATOR_BY)) {
                $magicFn = substr($magicFn, 0, -strlen(self::SEPARATOR_BY));
            }
        } elseif (!str_ends_with($magicFn, self::SEPARATOR_BY)) { // "Find" method without "By" must include a criteria to call __findBy
            $magicFn .= self::SEPARATOR_BY;
            array_unshift($magicArgs);
        }

        $magicFn = "__" . $magicFn;
        $magicArgs[0] = array_merge($magicArgs[0] ?? [], $this->criteria ?? []); // Criteria
        $magicArgs[0] = array_merge($magicArgs[0], array_transforms(fn($k, $v): array => ["special:" . $k, $v], $magicExtra));

        // Remove criteria for findAll requests..
        if (str_starts_with($magicFn, self::REQUEST_FIND . self::SPECIAL_ALL)) {
            array_shift($magicArgs);
        }

        $this->request = $requestType;
        $this->special = $special;

        return [$magicFn, $magicArgs];
    }
}
