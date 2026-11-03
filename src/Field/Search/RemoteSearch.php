<?php

namespace Base\Field\Search;

use Base\Database\Type\EnumType;
use Base\Database\UnitOfWorks;
use Base\Service\Icons;
use Base\Service\MediaServiceInterface;
use Base\Service\Model\Autocomplete;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\PaginatorInterface;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Tokens;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;

/**
 * A select's remote search, answered for any host (plan "omnibase agnostic", step F5d): what
 * omnibase's AutocompleteController answered under Symfony, moved here as it was, so that every
 * host searches a select's records and an icon picker's icons from the same addresses
 * (ROUTES) with the same answers.
 *
 * - data(): what a select hands its search, obfuscated (Obfuscator, short form: an identifier of
 *   the payload kept in its cache) - the class, the fields, the filters, the case, whether the
 *   entries are HTML and show faces, and a token of intent "select2" (the Tokens contract) that
 *   the search checks before answering. SelectType gives it under Symfony, FormView elsewhere.
 * - records(): the records of the class (its repository's cacheByInstanceOf(), the finder
 *   language), paged by the Paginator, each resolved by Autocomplete and kept when its text
 *   holds the term; an enumeration's values; {pagination: {more, page}, results}.
 * - icons(): an icon adapter's icons for the term, paged the same way.
 *
 * Asked in debug, both read the term and the page from the query or the body, GET or POST;
 * otherwise from the body of a POST only. The host's controller - Symfony's
 * AutocompleteController, omnibridge/laravel's SearchController - passes the request's parts
 * and sends the Answer as JSON; the profiler stays its own.
 */
class RemoteSearch
{
    /**
     * The search routes, in the order AutocompleteController declares them: name => [path,
     * action]. ux_autocomplete_forex (ForexType's rates) stays Symfony's.
     */
    public const ROUTES = [
        'ux_autocomplete' => ['/ux/autocomplete/{data}', 'records'],
        'ux_autocomplete_icons' => ['/ux/autocomplete/{provider}/{pageSize}/{data}', 'icons'],
    ];

    /** The intent of the token a search checks. */
    public const TOKEN = 'select2';

    /** The entries' words (a record's name, an enumeration's); null where the host has no omnibase translator: icons only. */
    private readonly ?Autocomplete $autocomplete;

    public function __construct(
        private readonly ObfuscatorInterface $obfuscator,
        private readonly Tokens $tokens,
        private readonly PaginatorInterface $paginator,
        private readonly UnitOfWork $unitOfWork,
        ?TranslatorInterface $translator = null,
        ?MediaServiceInterface $mediaService = null,
        private readonly ?Icons $icons = null,
        private readonly ?bool $debug = null,
    ) {
        $this->autocomplete = null !== $translator ? new Autocomplete($translator, $mediaService) : null;
    }

    /**
     * What a select hands its search (the route's `data`), as SelectType built it.
     *
     * @param list<string>|array<string, string> $fields  the columns the term is compared with
     * @param list<class-string>                 $filters the classes the records are instances of; none for every one
     */
    public function data(?string $class, array $fields = [], array $filters = [], ?bool $capitalize = null, bool $html = false, bool $avatar = false): string
    {
        return $this->obfuscator->encode([
            'class' => $class,
            'fields' => $fields,
            'filters' => $filters,
            'capitalize' => $capitalize,
            'html' => $html,
            'avatar' => $avatar,
            'token_name' => self::TOKEN,
            'token' => $this->tokens->csrf(self::TOKEN),
        ], ObfuscatorInterface::USE_SHORT);
    }

    /**
     * The filters of a select's search, as FormGuessTrait::guessChoiceFilter() guesses them
     * without the record: none when `choice_filter` is false, the class when it is null.
     *
     * @param array<string, mixed> $options
     *
     * @return list<class-string>
     */
    public static function filters(array $options): array
    {
        $filter = \array_key_exists('choice_filter', $options) ? $options['choice_filter'] : false;
        if (false === $filter) {
            return [];
        }
        if (null === $filter) {
            return \is_string($options['class'] ?? null) ? [$options['class']] : [];
        }

        return array_values((array) $filter);
    }

    /**
     * A page of records, or of an enumeration's values.
     *
     * @param array<string, mixed>                       $query      the request's query string
     * @param array<string, mixed>                       $body       the request's body
     * @param (\Closure(class-string): object)|null      $repository the repository of a class, the host's (by default the unit of work's)
     */
    public function records(string $data, string $method, array $query = [], array $body = [], ?\Closure $repository = null): Answer
    {
        $dict = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);
        if (null === $dict) {
            return Answer::refused('Unexpected request');
        }

        $fields = $dict['fields'] ?? null;
        $filters = $dict['filters'] ?? null;
        $class = $dict['class'] ?? null;
        $html = $dict['html'] ?? true;
        // Whether this particular field asked for profile pictures - see SelectType's `avatar`
        // option. Carried in the obfuscated payload so the dropdown and the already-selected
        // chips agree: a field showing faces in one and role icons in the other reads as a bug.
        $avatar = $dict['avatar'] ?? false;
        $format = self::format($dict['capitalize'] ?? null);

        $tokenName = $dict['token_name'] ?? null;
        if (!$tokenName || !$this->tokens->isValid($tokenName, $dict['token'] ?? null)) {
            return Answer::refused('Invalid token. Please refresh the page and try again');
        }

        $debug = $this->isDebug();
        if (!\in_array(strtoupper($method), $debug ? ['GET', 'POST'] : ['POST'], true)) {
            return Answer::refused('Invalid request');
        }
        if (null === $this->autocomplete) {
            return Answer::refused('No translator to name the entries (omnibase\'s Base\Service\TranslatorInterface)');
        }
        $term = (string) ($debug ? ($query['term'] ?? $body['term'] ?? '') : ($body['term'] ?? ''));
        $pageParam = (string) ($debug ? ($query['page'] ?? $body['page'] ?? '') : ($body['page'] ?? ''));

        $term = strtolower(str_strip_accents($term));
        $meta = explode('.', $pageParam);
        $page = max(1, \intval($meta[0] ?? 1));
        $bookmark = max(0, \intval($meta[1] ?? 0));

        $results = [];
        $pagination = false;
        if (\is_string($class) && $this->isEntity($class)) {
            $records = $this->repository($class, $repository);

            if (!is_associative($fields)) {
                $fields = array_fill_keys((array) $fields, $term);
            }
            $fields = array_filter($fields);

            $index0 = -1;
            $entries = $records->cacheByInstanceOf($filters, [], [], null, null, ['id']); // Term matching happens below via $search/$term; $fields only decides which columns feed that comparison.

            do {
                $bookIsFull = false;
                $book = $this->paginator->paginate($entries, $page);
                if ($page > $book->getTotalPages() + 1) {
                    break;
                }

                foreach ($book as $index => $result) {
                    $entry = $result['entity'] ?? null;
                    $entry = $this->autocomplete->resolve($entry, $class, ['format' => $format, 'html' => $html, 'avatar' => $avatar]);

                    if (null === $entry) {
                        continue;
                    }
                    if ($index0 < 0) {
                        $index0 = $index;
                    }
                    if ($index - $index0 < $bookmark) {
                        continue;
                    }

                    $search = strtolower(str_strip_accents(\strval($entry['search'] ?? $entry['text'])));
                    if (str_contains($search, $term)) {
                        $results[] = $entry;
                    }

                    $bookIsFull = \count($results) >= $book->getPageSize();
                    if ($bookIsFull) {
                        break;
                    }

                    ++$bookmark;
                }

                $bookmark = $bookmark % $book->getPageSize();
            } while ($page++ < $book->getTotalPages() && !$bookIsFull);

            $pagination = self::pagination($book, $bookIsFull);
        } elseif (\is_string($class) && is_subclass_of($class, EnumType::class)) {
            // Each value whose word holds the term (all of them when the payload names fields). The controller filtered
            // the parts of each entry instead of the entries, and failed on the first (a TypeError): fixed here.
            foreach ($class::getPermittedValues() as $value) {
                $entry = $this->autocomplete->resolve($value, $class, ['format' => $format, 'html' => $html, 'avatar' => $avatar]);
                if (null !== $entry && (!empty($fields) || str_contains(mb_strtolower(str_strip_accents(\strval($entry['text']))), $term))) {
                    $results[] = $entry;
                }
            }
        }

        return Answer::page(['pagination' => $pagination, 'results' => $results]);
    }

    /**
     * A page of an icon adapter's icons.
     *
     * @param IconAdapterInterface|string $provider the adapter, or its name (fa, bi) among the host's Icons
     * @param array<string, mixed>        $query
     * @param array<string, mixed>        $body
     */
    public function icons(IconAdapterInterface|string $provider, int $pageSize, string $data, string $method, array $query = [], array $body = []): Answer
    {
        $dict = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT) ?? [];

        $token = $dict['token'] ?? null;
        $html = $dict['html'] ?? true;
        $pageSize = $dict['page_size'] ?? 200;
        $format = self::format($dict['capitalize'] ?? null);

        $expectedMethod = $this->isDebug() ? 'GET' : 'POST';
        if (!$this->tokens->isValid(self::TOKEN, $token) || strtoupper($method) !== $expectedMethod) {
            return Answer::refused('Invalid request');
        }
        $adapter = \is_string($provider) ? $this->adapter($provider) : $provider;
        if (null === $adapter) {
            return Answer::refused('No icon adapter "'.$provider.'"', 404);
        }

        $term = mb_strtolower((string) ($query['term'] ?? $body['term'] ?? ''));
        $meta = explode('.', (string) ($query['page'] ?? $body['page'] ?? ''));
        $page = max(1, \intval($meta[0] ?? 1));
        $bookmark = max(0, \intval($meta[1] ?? 0));

        $entries = $adapter->getChoices($term);

        $results = [];
        $index0 = -1;
        do {
            $bookIsFull = false;
            $book = $this->paginator->paginate($entries, $page, $pageSize);
            if ($page > $book->getTotalPages() + 1) {
                break;
            }

            foreach ($book as $index => $result) {
                $entry = Autocomplete::resolveArray($result, ['format' => $format, 'html' => $html]);
                if (null === $entry) {
                    continue;
                }
                if ($index0 < 0) {
                    $index0 = $index;
                }
                if ($index - $index0 < $bookmark) {
                    continue;
                }

                $bookIsFull = count_leaves($results) >= $book->getPageSize();
                if ($bookIsFull) {
                    break;
                }

                ++$bookmark;
                $results[] = $entry;
            }

            $bookmark = $bookmark % $book->getPageSize();
        } while ($page++ < $book->getTotalPages() && !$bookIsFull);

        return Answer::page(['pagination' => self::pagination($book, $bookIsFull), 'results' => $results]);
    }

    /** The adapter of that name or class among the host's icons; null without one. */
    public function adapter(string $idOrClass): ?IconAdapterInterface
    {
        return $this->icons?->adapter($idOrClass);
    }

    /** @return array{more: bool, page?: string} */
    private static function pagination(object $book, bool $bookIsFull): array
    {
        $pagination = ['more' => $book->getTotalPages() > $book->getPage() || $bookIsFull];
        if ($pagination['more']) {
            $page = $book->getPage();
            $bookmark = ($book->getBookmark() + 1) % $book->getPageSize();
            if (0 == $bookmark) {
                ++$page;
            }

            $pagination['page'] = $page.'.'.$bookmark;
        }

        return $pagination;
    }

    private static function format(?bool $capitalize): int
    {
        return null === $capitalize ? FORMAT_IDENTITY : ($capitalize ? FORMAT_TITLECASE : FORMAT_SENTENCECASE);
    }

    private function isDebug(): bool
    {
        return $this->debug ?? (bool) Runtime::find(\Omnibridge\Contract\Environment::class)?->isDebug();
    }

    private function isEntity(string $class): bool
    {
        try {
            return UnitOfWorks::shapes($this->unitOfWork)->has($class);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The repository a picker looks into: the unit of work's for the class, or the host's
     * ($repository) - Symfony's builds one of omnibase's where the entity's own is a bundle's
     * plain repository, which answers no cacheByInstanceOf() (the search answered 500, the
     * picker stayed on "Searching...").
     *
     * @param (\Closure(class-string): object)|null $repository
     */
    private function repository(string $class, ?\Closure $repository): object
    {
        return null !== $repository ? $repository($class) : UnitOfWorks::repositories($this->unitOfWork)->of($class);
    }
}
