---
title: Entities and repositories
order: 25
---

# Entities and repositories

## Repositories

`Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository` is doctrine/orm's
`EntityRepository` built from the `ManagerRegistry` (doctrine/persistence), as
DoctrineBundle's service repository is - `__construct(ManagerRegistry $registry,
?string $entityName = null)`, the entity guessed from the repository's name -
plus the finder language (`findByStateAndLocale()`, `cacheOneBySlug()`,
`countByDomain()`...). It does not extend DoctrineBundle's class: a repository
loads without the framework (it is in `tests/Foundation.php`). Under Symfony
every service extending it is tagged `doctrine.repository_service`
(`BaseExtension`, `registerForAutoconfiguration()`), so an application's
`App\Repository\*` is still handed out by `getRepository()` and autowired.

**Counting gives an integer.** `count([])`, `count(['state' => 'draft'])`,
`countByState('draft')` and `distinctCountForLocaleByDomain('messages')` each
run one `SELECT COUNT(...)` and return an `int`, as Doctrine's `count()`
promises. Only a grouped count (a `$groupBy` or a `$selectAs` argument) returns
rows, each with its `count` (`ThreadRepository::countForChildrenIn()`).

**What is registered as a repository.** A bundle maps its `src/Repository`
(`setMapping()` in its Bundle class): every class there is aliased under the
application's namespace, and the ones that are repository services are
registered (`doctrine.repository_service`, built with `doctrine`). A repository
service is a class whose name ends with `Repository`, that can be instantiated
and extends omnibase's `ServiceEntityRepository` or implements DoctrineBundle's
`ServiceEntityRepositoryInterface` (`AbstractBaseBundle::isRepositoryService()`). An abstract base repository, an
interface or a trait in that directory is aliased, never registered: a bundle
may keep `AbstractCatalogueRepository` beside the repositories that extend it.

**Repositories behind interfaces.** The code of a site and of the core asks
its repositories through interfaces named by what they answer, in the
namespace of their entity (audit "lesringostars face au master plan", step R15):
`Base\Database\Repository\Entities` - what every repository answers:
`find()`, `findAll()`, `findBy()`, `findOneBy()`, `count()`, `persist()`,
`remove()`, `flush()` -, and one per entity: `Base\Repository\Threads`,
`Users`, `Thread\Tags`, `User\Notifications`, `Layout\Settings`; a site
adds its own beside its repositories (`App\Repository\Article\Articles extends
Threads`). Nothing of the ORM in what they take or give back: arrays of
criteria and of order (`field => ASC|DESC`), the entities, lists of them,
integers. The Doctrine repository implements its interface, each method by
the finder call it replaces (`Threads::oneBySlugAndState()` is
`cacheOneBySlugWithRouteAndState()`): same query, same result cache. Under
Symfony each interface is an alias of the repository service
(`config/services/database.php`; a site's own by `#[AsAlias]` on its
repository); under Laravel the application binds each to the repository
omnibridge/laravel resolves by its class. Another persistence implements the
same interfaces over the same tables (the structure: [schema.md](schema.md)).

The finder language (`cacheOneBySlug()`...) stays the repositories' own: an
application calls the interface's methods, never the finder through it.

The interfaces are listed in `Base\Repository\Aliases::INTERFACES` (29 with
`Repositories`, since step E2' of the plan "omnibase agnostic": `User\Complaints`,
and `Thread\Comments` answering what omnibase/blog asks - `findVisible()`,
`countVisible()`, `countPending()`, `findPending()`). A site or a bundle types
on them, not on a repository's class: since E2' the core's bundles and
lesringostars do, but for omnibase/admin's dashboard tile of complaints, which
keeps `ComplaintRepository` while the bundle serves omnibase 4.x as well (4.x has
no `Complaints`).

## The finder language, read without Doctrine

A finder's name - `findOneByEmailAndUsername()`, `cacheOneByStateAndSlugStartingWith()`,
`countByStateAndPublishedAtYoungerThan()`, `cachePreviousOneByClassOfAndState()`,
`lengthOfTagsByState()` - is read by `Base\Database\Repository\Finder\Parser`,
which knows nothing of an ORM (plan "omnibase agnostic", 2026-11-01, step F1).
Its `parse($method, $arguments)` gives a `Finder\Query`, the language's neutral
form:

- `method` and `arguments`: the internal request the name leads to
  (`__findBy`, `__findOneBy`, `__findLastBy`, `__findAtMostBy`,
  `__findPreviousBy`, `__findNextBy`, `__findRandomlyBy`, `__findAll`,
  `__countBy`, `__distinctCountBy`, `__lengthOfBy`...) and its arguments - the
  criteria first (those the name declares, `"field:index" => value`, merged into
  the array given, then the specials' numbers and keys as `"special:..."`), then
  the order, the limit, the offset, the grouping, the selected expressions;
- `criteria` and `options`: the criteria the name declares and their words
  (`EqualTo`, `NotEqualTo`, `GreaterThan`, `LowerThan`, `YoungerThan`,
  `OlderThan`, `Within`, `IsOver`, `StartingWith`, `Null`, `Empty`, `True`,
  `Insensitive`, `Partial`, `ClosestTo`, `InstanceOf`, `ClassOf`, `MemberOf`...);
  `criterions()` gives them as `Finder\Criterion` objects (field, index, value,
  options), `extras()` the specials' numbers and keys;
- `separator` (`And` or `Or`), `column` (`countFor<Column>By`,
  `lengthOf<Column>By`, `sumOf<Column>By`...), `cacheable`, `eagerly`, `request` (find, count,
  lengthOf, sumOf, minOf, maxOf) and `special` (One, All, Randomly, AtMost, Last, LastOne, Previous,
  PreviousOne, Next, NextOne).

What the parser asks of the entity is a `Finder\Subject`: its class, its fields
(a field whose name holds a word of the language is refused), whether it has a
field or association, a Query-by-Example's criteria. A host's request given to a
`...WithRoute` finder is turned into route parameters by the resolver the parser
is given (`Request` and `KernelEvent` under Symfony).

A persistence compiles the Query into its own queries. On Doctrine it is
`ServiceEntityParser`: the Subject of its entity, `read()` (the parse alone) and
`execute()` (the DQL, the joins, the date bounds, the DQL functions, the caches,
the eager loading); `parse()` does both, as `ServiceEntityRepository::__call()`
asks. The compiler is omnibridge/doctrine's since step F3
(`Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityParser`, its former
name aliased by the core, see [the Doctrine mapping](doctrine.md)), and
omnibridge/eloquent compiles the same Query into Illuminate's query builder at
F7: the language is one, whatever the backend.

The parse is the one of before F1, word for word:
`tests/Database/Repository/FinderParityTest` compares, for 57 finder calls (the
core's own shapes and one of each word), the parse and the query (DQL,
parameters, first and maximum results, or what a running finder gave or threw)
with `finder-parity.json`, recorded on the code of before
(`FINDER_PARITY_RECORD=1`). lesringostars' `FinderParityTest` compares the
results on the site's data. `tests/Database/Repository/Finder/ParserTest` reads
each word on an entity known by its fields alone, and
`BareTest::testTheFinderLanguageIsReadWithoutDoctrine` parses under
`bare --strict`.

Each word does what its name says (fixed before step F2,
`tests/Database/Repository/FinderDefectsTest`): `LowerEqualTo`, `GreaterEqualTo`, `OlderEqualTo`
and `YoungerEqualTo` compare with `<=` and `>=`; a negative word is read before the positive one it
ends with (`NotStartingWith`, `NotEndingWith`, `NotInstanceOf`, `NotClassOf`, `NotMemberOf`), and
`NotClassOf` is `ClassOf`'s opposite (anything but that class exactly); `lengthOf<Collection>By`
gives each entity's number of members (`SIZE()`), `lengthOf<Field>By` its length (`LENGTH()`); a
`DateTimeImmutable` compared with a field of a mutable type is bound as the same moment; `Null` and
`NotNull` bind no parameter.

## Groups, aggregates, and the queries one persistence runs (step E2)

A repository's method is written in the finder language wherever it can be, so that every
persistence compiles it (plan "omnibase agnostic", step E2; the inventory of the repositories'
hand-written queries, each sorted, is in the plan). Two features were added for it:

- **Groups.** A name joins its criteria with one separator, `And` or `Or`. The parentheses a
  name cannot say are an entry of the criteria array, `Finder\Group::by(<name>, ...values)`, read
  by the same parser as the criteria part of `findBy<name>`, its own separator inside:

  ```php
  $notifications->count(['user' => $user, 'isRead' => false,
      Group::by('TitleNotEmptyOrSubjectNotEmptyOrContentNotEmpty')]);
  $translations->findByTranslatableNotNull([
      Group::by('InsensitivePartialTitleOrInsensitivePartialExcerpt', '%word%', '%word%'),
      'translatable.state' => ['STATE_PUBLISH'],
  ]);
  ```

  Every word of a criterion is allowed in a group; not what is no condition (`ClosestTo`,
  `FarestTo`, `Model`), `WithRoute`, nor a criteria array or any argument after the named values.
  `Query::criterions()` gives a group as one criterion whose value is the `Group` read
  (`Group::criterions()`, `Group::separator()`); omnibridge/doctrine compiles it into a
  parenthesised expression whose parameters and options are its own.
- **Aggregates.** `sumOf<Column>By...`, `minOf<Column>By...` and `maxOf<Column>By...` give `SUM()`,
  `MIN()` or `MAX()` of a field over the rows the criteria select (`sumOfViewsByDateGreaterEqualTo($since,
  ['path' => '/'])`, `minOfDate()`): the database's value as it gives it, `null` without a row
  (`Query::$request` `sumOf`, `minOf`, `maxOf`; internal `__sumOfBy`...).

omnibridge/doctrine's compiler also binds an equality on one value with the field's type when
that type converts it (`ConvertedInCriteria`: the passkeys' base64 and AAGUID columns), as
Doctrine's own `findBy()` does: `findOneBy(['publicKeyCredentialId' => $bytes])` finds the row.

What the language does not say stays the backends', declared so:
`#[Base\Database\Repository\BackendOnly(['doctrine', 'eloquent'], reason)]` on the core's interface
method - the analytics' native SQL (upserts, `INSERT IGNORE`, `DATE()` groupings, correlated
subqueries) and `Redirections::hit()`'s bulk `UPDATE`. No finder compiles them: each persistence
writes them in its own terms, omnibridge/doctrine through DBAL (its implementation declares
`BackendOnly('doctrine', ...)` too), omnibridge/eloquent the same statements through Illuminate's
connection (step F7f; `Redirections::hit()` as one `UPDATE ... SET hits = hits + 1`).
`BackendOnly::declared()` lists them (14, kept in `tests/Database/Repository/BackendOnlyTest`),
`BackendOnly::runs()` tells whether a persistence runs one, and a persistence not named answers
`BackendOnly::refuse()` - an `UnsupportedQueryException` that names the method, the backends it is
written for and why - until it writes the query in its own terms, never a silent wrong answer.
None of the 14 is tied to Doctrine itself: plain SQL a connection runs, and an `UPDATE` whose
`hits + 1` the database computes (no DQL-only construct, no Doctrine locking).

Each rewritten method gives the rows of its former query, in the same order:
`FinderParityTest::testTheRewrittenQueriesGiveTheRowsOfBefore` runs both (the former queries
kept word for word in `tests/Database/Repository/HandWrittenQueries.php`) on rows it writes in the
harness, and lesringostars' FinderParityTest runs them on the site's data. Under omnisense/omnibase
4.x, whose finder has neither groups nor aggregates, omnibridge/doctrine's methods written on them
keep their former query (`ServiceEntityRepository::finderReadsGroups()`).

## An association compared by its key, without a join (step E2b)

A criterion on an association is compiled through a join of it (`LEFT JOIN e.thread e_thread`,
`e_thread = :thread`), and a persistence that scopes the joined entity puts its scope there:
Doctrine's trash filter on Thread goes into the join's `ON`, so a comment whose thread is in the
trash reads as a comment without a thread. `Finder\Key::of($value)`, a criterion's value,
compares the row's own foreign key instead, with no join:

```php
$comments->findBy([
    'state' => CommentState::APPROVED,
    'thread' => Key::of($thread),   // e.thread = :key_thread; null: e.thread IS NULL
    'parent' => Key::of(null),
], ['createdAt' => 'DESC'], 200);
$comments->findByThreadNotEqualTo(Key::of([3, 4]));   // e.thread NOT IN (:key_thread_0)
```

It takes an entity, its identifier, a list of them (`IN`; an empty list leaves the criterion out,
as any list of the language) or `null`, with `EqualTo` (the default) or `NotEqualTo` only, on an
association of the entity itself; the Parser refuses a path (`thread.parent` needs the join) and
any other word, the compiler a field that is no single-valued association. In the criteria array,
in a name's arguments or in a group alike; `Criterion::byKey()` tells a compiler.
omnibridge/doctrine compiles it to `e.<association>` (`=`, `<>`, `IN`, `NOT IN`, `IS NULL`,
`IS NOT NULL`), omnibridge/eloquent to the foreign-key column. Not a word of the name: "Key" in a
name would make every field holding the word (Passkey's `publicKeyCredentialId`) one the
language refuses.

`Comments::findVisible()` and `countVisible()` are written on it: the comments of a thread in the
trash stay its own, as with their former query (FinderParityTest writes them, beside a live
thread's and the visitors' book's). Under omnisense/omnibase 4.x, which has no `Key`, the
methods keep their former query (`ServiceEntityRepository::finderReadsKeys()`).

## Translations

An entity using `TranslatableTrait` reads and writes its translated fields
through `translate()`:

```php
$post->translate('de')->setTitle('Apfel');   // written in German, persisted at the next flush
$post->translate('ja')->getTitle();          // null when nothing is written in Japanese; creates nothing
$post->translate()->getTitle();              // what the current page shows
```

- `translate($locale)` is that locale's translation. When the entity has none,
  it is a new, empty one the entity holds aside (out of `getTranslations()`)
  until a value is written in it: **a read creates no translation**. A written
  one joins the collection at the next `getTranslations()` or flush
  (`commitPendingTranslations()`, called by `IntlSubscriber` before each flush).
- `translate()` without a locale is the translation to show on the current
  page: the page's language when something is written in it, else the same
  language in another region (`pt-BR` on a `pt-PT` page), else the **default
  locale's**, else the first available locale's, else any written one.
- The magic getters (`$post->getTitle()`, `post.title` in Twig) read
  `translate()`, then the default locale when the value is null.

An `isEmpty()` translation is never persisted (`IntlSubscriber::onFlush`).

## Countries

`{{ code|country_name }}` names a country from its ISO 3166-1 code, in the
page's language; `{{ code|country_name('de') }}` in the one given
(`Localizer::getCountryName()`, on `symfony/intl`). `JP` is "Japon" on a French
page; alpha-3 (`JPN`) is read too; an unknown code is printed as it is. The
country of a locale (`fr-BE` → "Belgium") is `|locale_country_name`.

## A taxon's slug is unique per type

`Base\Entity\Thread\Taxon` is the root of a JOINED hierarchy: a FAQ's
`Section`, a classroom's `Subject`, a menu's `MenuSection`, a site's own
`Category` all share the table `threadTaxon` and its `slug` column. The slug
is unique **among the taxa of one type** - the unique index is on
`(class, slug)`, `class` being the discriminator - so a menu's section and a
blog's category may both be `desserts`. (It was unique on the column alone:
the second one became `desserts-2`, whatever its type.)

`#[Slugify(reference: 'translations.label', perType: true)]` is what numbers
a slug only against the entities of the same class; any hierarchy that shares
a slug column may use it.

**The migration an application generates** after updating omnibase
(`bin/console doctrine:migrations:diff`), on `threadTaxon`: the unique index
on `slug` is dropped, a unique index `thread_taxon_type_slug` on
`(class, slug)` and a plain index `thread_taxon_slug` on `slug` are created.
No row changes; the new constraint is weaker than the old one, so it always
applies. Until that migration has run, omnibase sees the old index and keeps
numbering slugs for all types together: nothing fails in between.

A taxon is found by its slug through the repository of its type
(`SectionRepository::findOneBySlug()`): `TaxonRepository` itself may now find
several types under one slug.

## The second-level cache after the fixtures

A site that turns on Doctrine's second-level cache
(`doctrine.orm.second_level_cache`) caches its entities by id. A command that
rewrites the database behind the ORM's back leaves that cache wrong:
`doctrine:fixtures:load` purges the tables with `DELETE` / `TRUNCATE`, the
new rows are numbered from 1 again, and the cache answered yesterday's entity
for today's id until it expired.

`Omnibridge\Symfony\Omnibase\Subscriber\SecondLevelCacheConsoleSubscriber` empties every region
(entities, collections, queries) of every entity manager when one of these
commands ends: `doctrine:fixtures:load`, `doctrine:schema:{drop,create,update}`,
`doctrine:database:{drop,import}`, `doctrine:migrations:{migrate,execute}`,
`dbal:run-sql`. Nothing to configure; `cache:pool:clear` by hand after loading
fixtures is no longer needed. A command of your own that writes SQL directly
calls it: `$subscriber->evict()`.
