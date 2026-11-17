# Changelog

## 7.0.4

- `cache:clear`'s automatic second clear ran while the first still held the cache folder's lock: the second process
  skipped ("Another cache:clear is already running"), so on a fresh tree one `cache:clear` left the clear counter at
  one and made none of the public folder's links (`public/uploads`, `images`, `wysiwyg`); a local upload then had no
  public address until a second manual `cache:clear`. The lock is released before the second clear starts.

## 7.0.3

- No `extra.branch-alias` any more (the ensemble has none, foundation Q4): the release workflow no longer checks the
  tag against one (`branch-alias: false`); the branch 7.x is Composer's 7.x-dev as it is.
- The harness image installs omnibridge/eloquent beside omnibridge/doctrine (`Base\Testing\Engines`, the parity tests
  of the core and the plugins) and its application names its persistence (`base.persistence: doctrine`).
- Tests: BareTest accepts the src/Base a persistence bridge declares under `Base\` (omnibridge/doctrine 3.0); the
  recorded Doctrine metadata and finder parse follow the 7.0 ensemble (forge's seat listener is a neutral lifecycle
  listener, TimeEntry and Wishlist\Reservation name their repositories, the filters are omnibridge/doctrine's,
  a finder asked of `Base\Entity\User` queries that class, `ServiceEntityParser` is `DqlCompiler`).

## 7.0.2

- `Base\Database\Repository\EntityRepositories`: its PHPDoc takes any repository, `ServiceEntityRepository<covariant
  object>` (`register()`, `repositoryClass()`, the registry): 7.0.1's `ServiceEntityRepository<object>` refused a
  repository of a given entity (`Base\Repository\ThreadRepository`, `ServiceEntityRepository<Thread>`), as
  omnibridge/eloquent's PHPStan reported.
- The harness image (docker/Dockerfile) no longer requires `omnibridge/doctrine:1.x-dev` among its bundles, which
  conflicted with the 3.x it requires and with the 2.0 bundles: the release's harness job built no image.

## 7.0.1

`Base\Mapping\OrderBy`'s value is documented as `array<string, string|\UnitEnum>`: a direction may be an enumeration
(`SortDirection::Ascending`), as omnibase/mailbox writes it; 7.0.0's PHPDoc took strings only, and an application's
PHPStan reported the enumeration.

## 7.0.0

omnisense/omnibase: the core of omnibase sites under the agnostic `Base\` namespace (an application overrides
`Base\X` by `App\X`, the same path), on any framework and persistence: the entities (written in `Base\Mapping`), the
agnostic repositories (`Base\Repository\*` over `Base\Database\Repository\ServiceEntityRepository`: the finder
language, `findBy()`'s criteria array, `count()`, `findValuesBy()`, `updateBy()`, `deleteBy()`, `lockOneBy()`, `sql()`: every
query written once; the same classes on
Doctrine and on Eloquent, the engine given by `Finders`), one `Base\Service\Paginator` (a repository with criteria and
an order, a finder's result, or rows), the behaviour attributes (`Base\Database\Attribute\*`) on the neutral
lifecycle, the generic contracts of omnisense/omnibridge under `Base\Contract\*`, the options (`base.*`), the
services, templates, translations and assets. The framework and ORM glue lives in the omnibridge/* bridges, which
add their classes under `Base\` too; a site or a bundle names only `Base\` and `App\`.
