# Changelog

## Unreleased (1.0.0)

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
