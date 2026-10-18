# Base bundle

Bedrock Symfony bundle for Glitchr projects: it pre-configures a full application
stack (admin, security, media, i18n, workflow, API) so host apps only add their
domain code. The `Base\` namespace deliberately mirrors the host apps' `App\`
structure — a host class can usually extend or decorate its `Base\` counterpart
one-to-one.

Published as `omniscient/omnibase` (formerly `glitchr/omnibase`, which this package replaces: a
`composer.json` that still requires the old name keeps resolving to it, and never installs both).
Composer now installs it in `vendor/omniscient/omnibase`; nothing in the package depends on that
path (its templates, documentation and scripts are found from its own directory), but an
application that names the old path in its configuration has to follow - see CHANGELOG.

Requires PHP ≥ 8.2 (omnibridge/doctrine). Since 4.0 the package requires its Fondation alone
(Doctrine, Twig, Symfony's standalone components): under Symfony 6, 7 or 8 the framework comes with
`omnibridge/symfony`, see Installation. Licensed MIT (`LICENSE`) since 2026-10-09;
earlier versions remain published under LGPL-3.0-or-later.

## Docker: every plugin, running

`docker/` builds this checkout into one Symfony application with every `omnibase/*` plugin -
the back office, the shop, the forge, the forum, the mailbox, the docs - on SQLite, with a demo
page that seeds members and a shop and signs you in:

```sh
cd docker
docker compose up                                        # → http://localhost:8000/
docker compose run --rm omnibase check                   # container and templates linted, routes counted
docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase bare   # the Fondation loaded without the framework
docker compose -f compose.yml -f compose.checkouts.yml up   # the checkouts beside this one instead of Packagist
docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase test [admin|marketplace|forge|...]   # a suite, on the checkouts
```

The suites run there in the test environment on a fresh SQLite database: see
[docs/30-operations/harness.md](docs/30-operations/harness.md).

## Installation

The package is resolved from Packagist (repository: https://github.com/glitchr-studio/omnibase).

**Symfony** (4.0, branch `4.x`): the core and the framework packages, which `omnibridge/symfony`
carries (FrameworkBundle, DoctrineBundle, SecurityBundle, TwigBundle, MonologBundle, Form...),
then the bundles your `config/bundles.php` registers - the core suggests them, and works with
all of them:

```bash
composer require omniscient/omnibase:4.0.*-dev omnibridge/symfony:1.x-dev
composer require scheb/2fa league/flysystem-bundle hwi/oauth-bundle web-auth/webauthn-symfony-bundle \
    api-platform/symfony api-platform/doctrine-orm symfony/webpack-encore-bundle symfony/ux-twig-component \
    glitchr/ux-google glitchr/well-known nelmio/cors-bundle nelmio/security-bundle
```

Without any one of them the container still compiles: since step C7b `Base\Entity\User`
declares Security's and scheb/2fa's interfaces where they are installed (`Base\Contract\SecurityUser`,
`TwoFactorUser`), and `Base\Service\Flysystem` is the core's own class, on omnibase's local storages
without league/flysystem-bundle. An application coming from 3.x adds `omnibridge/symfony` and
the bundles it did not require itself, and updates those alone. The 3.x branch
(`omniscient/omnibase:3.0.*-dev`) still requires the framework itself. See
[docs/20-architecture/packages.md](docs/20-architecture/packages.md).

**Laravel**: later, with `omnibridge/laravel`, which fills the core's contracts with Laravel's
services; no Symfony framework package is installed then.

Enable in `config/bundles.php` (Flex usually does it):

```php
Base\BaseBundle::class => ['all' => true],
```

Bundle configuration lives under the `base:` key; the tree is defined in
`src/DependencyInjection/BaseConfiguration.php`.

## Try it in one command

A self-contained demo (bare Symfony skeleton + this bundle + SQLite, one flat
page touring the `App\ ↔ Base\` mirroring, the shipped entity layer, the
setting bag, the obfuscator and the translator) ships in the root
[Dockerfile](Dockerfile):

```bash
docker build -t base-bundle-demo .
docker run --rm -p 8000:8000 base-bundle-demo
# → http://localhost:8000/
```

The demo app under [example/app/](example/app/) doubles as a minimal
reference: the smallest `bundles.php`, `base.yaml`, `doctrine.yaml`,
`security.yaml` and `flysystem.yaml` a host application needs.

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

The bundle is developed inside a host app's `vendor/omniscient/omnibase`
checkout (composer VCS install keeps `.git`) and pushed from there.

```bash
make build     # yarn install --frozen-lockfile (yarn.lock) + Encore, production build
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
