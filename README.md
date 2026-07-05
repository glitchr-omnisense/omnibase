# Base bundle

Bedrock Symfony bundle for Glitchr projects: it pre-configures a full application
stack (admin, security, media, i18n, workflow, API) so host apps only add their
domain code. The `Base\` namespace deliberately mirrors the host apps' `App\`
structure — a host class can usually extend or decorate its `Base\` counterpart
one-to-one.

Requires PHP ≥ 8.1 and Symfony 6, 7 or 8. Licensed LGPL-3.0-or-later
(see `COPYING` / `COPYING.LESSER`).

## Installation

The package is resolved from GitLab (`gitlab.glitchr.dev/public-repository/symfony/bundle/base/component`):

```bash
composer require glitchr/base-bundle:^3.0
```

Enable in `config/bundles.php` (Flex usually does it):

```php
Base\BaseBundle::class => ['all' => true],
```

Bundle configuration lives under the `base:` key; the tree is defined in
`src/DependencyInjection/BaseConfiguration.php`.

## What's inside (map of `src/`)

| Area | Directories | Purpose |
|------|-------------|---------|
| Admin | `Admin/`, `Field/`, `Form/` | EasyAdmin integration: custom CRUD fields (~40, e.g. `WysiwygField`, `CropperField`, `TranslationField`) with their configurators, form types and extensions |
| Persistence | `Database/`, `Entity/`, `Repository/`, `EntityDispatcher/`, `*Subscriber/` | Doctrine attributes (`Hierarchify`, `Versionable`, `Vault`, …), base entities (User, Thread, Layout, …), lifecycle dispatching |
| HTTP | `Controller/`, `Routing/`, `Response/` | Public/admin/UX controllers, advanced router, sitemap |
| Security | `Security/`, `Validator/` | Voters, 2FA (scheb), access tokens, dynamic session storage |
| Services | `Service/`, `Cache/`, `Notifier/`, `Serializer/` | `BaseService` facade, media/file/flysystem services, localizer, notifier channels |
| Presentation | `Twig/`, `templates/`, `translations/` | Twig extensions/components, EasyAdmin + client templates, translations |
| Assets | `assets/`, `public/`, `Imagine/` | Encore entrypoints (async/defer), built assets, image filters |
| Tooling | `Console/`, `Inspector/`, `Attributes/` | Console commands, profiler data collectors, attribute reader |

Extension points are autoconfigured by tag — implement the interface, the tag is
applied automatically (see `src/DependencyInjection/BaseExtension.php`):
`base.entity_extension`, `base.annotation`, `base.icon_provider`,
`base.service.sharer`, `base.simple_cache`, …

## Service wiring

Service definitions live in `config/`:

- `services.php` — registrations (split per domain, loaded by `BaseExtension`)
- `services-decoration.php` — decorations of framework/third-party services
- `services-public.php` — services forced public
- `services-fix.php` — targeted workarounds

## Development

The bundle is developed inside a host app's `vendor/glitchr/base-bundle`
checkout (composer VCS install keeps `.git`) and pushed from there.

```bash
make build     # yarn install + Encore (watch in debug, prod otherwise)
make linter    # php-cs-fixer + phpstan (level max)
make tests     # phpunit
```

CI (`.gitlab-ci.yml`) runs cs-fixer, PHPStan and PHPUnit on every push.

## Releases

One development branch per major (`1.x`, `2.x`, `3.x` — the current one is the
main branch; the next major starts `4.x`), tags per release (`3.0.0`, `3.1.0`,
…) — tag after each meaningful batch so consumers can pin stable versions:

```bash
git tag -a 3.1.0 -m "..." && git push origin 3.1.0
```

The legacy `1.0`/`2.0`/`3.0` branches carry the pre-2026 unslimmed history and
are frozen — do not push to them.

See `CHANGELOG.md`.
