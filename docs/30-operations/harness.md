---
title: The Docker harness
order: 50
---

# The Docker harness

`docker/` builds this checkout into one Symfony application with every
`omnibase/*` plugin (admin, marketplace, forge, forum, mailbox, docs), paying
through `glitchr/omnitrade`, shipping through `glitchr/omnibus` and signing through
`glitchr/omnisign` (a DocuSeal gateway, its recorded answers in the tests), on SQLite.
It is where the bundles' suites run: there is no PHP on the host.

```sh
cd docker
docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase test              # omniscient/omnibase's suite
docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase test admin        # a plugin's: admin, marketplace, forge...
docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase test forge --filter License
docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase check             # container and templates linted
docker compose -f compose.yml -f compose.checkouts.yml run --rm omnibase bare              # the Fondation loaded without the framework
docker compose -f compose.yml -f compose.checkouts.yml up                                  # the demo, http://localhost:8000/
```

`compose.checkouts.yml` mounts the checkouts beside this one (`../admin`,
`../marketplace`...) over the installed packages: what is edited is what runs,
no rebuild needed. Without it, the plugins are the Packagist ones.

## What `test` sets up

- **The test environment.** `APP_ENV=test`; PHPUnit starts from
  `tests/harness.php`, which loads `.env` then `.env.test` (as an application's
  own `tests/bootstrap.php` does), then the suite's own bootstrap (it registers
  the suite's test namespace). `docker/app/.env.test` holds test values for
  every variable the bundles' configuration reads: `KERNEL_CLASS`,
  `DATABASE_URL`, `DEFAULT_URI`, `CORS_ALLOW_ORIGIN`,
  `GOOGLE_RECAPTCHA_SITE_KEY` / `GOOGLE_RECAPTCHA_SECRET` (Google's public test
  keys), `MAILER_DSN`, `MAILER_CONTACT`, `MESSENGER_TRANSPORT_DSN`, the Stripe
  keys (empty). A suite that dies before its first test on a missing variable
  wants a line there.
- **A fresh database.** `var/test.db` (SQLite) is deleted, and its schema
  created from every mapped entity, at each run; the test container cache
  (`var/cache/test`) too.

The suites that need a host application (`KernelTestCase`: core's analytics,
admin's widgets, the forge's entities) run here as they do inside an
application. Those that need rows to look at skip when the fresh database has
none.

## The Fondation: `bare`

`tests/Fondation.php` lists the classes of the core that load without the
framework, sorted. `tests/BareTest.php`, part of the suite, runs
`docker/bin/bare` in a PHP process of its own: Composer's autoloader alone - no
kernel, no container, no bundle booted - then each class of the list, and every
class, interface, trait and file PHP loaded on the way is checked. None may be
the framework's: the Symfony bundles (FrameworkBundle, DoctrineBundle,
TwigBundle, SecurityBundle, every `*-bundle`) and bridges, `dependency-injection`,
`config`, `http-kernel`, `http-foundation`, `routing`, `security-*`, `form`,
`console`, `ux-*`, API Platform, HWI, Scheb, Webauthn's bundle, Nelmio. The
standalone Symfony components (`string`, `uid`, `intl`, `translation`,
`validator`, `serializer`, `mime`, `mailer`, `notifier`, `cache`, `workflow`,
`event-dispatcher`, `http-client`, the contracts...), Doctrine (storage) and
Twig (the view) are allowed. A failure names the framework's class or file, the
class of the list that loaded it and the class that asked for it. A second case
loads `Base\BaseBundle` and `Base\Repository\UserRepository` the same way and
checks the report names the framework (`HttpKernel\Bundle\Bundle`, asked by
`AbstractBaseBundle`; Security's `UserProviderInterface`, implemented by the
users' repository): the check is not blind. A third loads a repository
(`ThreadRepository`): doctrine/orm's `EntityRepository`, nothing of the
framework (plan "omnibase agnostique", C3b).

```sh
docker compose run --rm omnibase bare                      # the Fondation; exits 1 when the framework was loaded
docker compose run --rm omnibase bare 'Base\Entity\User'    # the classes named
docker compose run --rm omnibase bare --all --each         # every class of src/, each in a process of its own: those that can join
docker compose run --rm omnibase bare --json               # the whole report
docker compose run --rm omnibase bare --names              # informative: the classes of the Fondation that name the framework
```

What is checked is what loading a class loads - its parents, interfaces,
traits, what its file runs - not what its methods do when called: an
attribute's class, a type in a signature and a `new` in a method body load
nothing until they run. Composer's autoloader itself includes
`api-platform/jsonld`'s `HydraContext.php` (a constant, a `files` entry) before
any class: it is reported, not held against one.

**The list only grows.** A class enters it when it loads without the framework
(`bare --all --each`); a class leaves it only by a decision written down in the
plan "omnibase agnostique" and in CHANGELOG.md. On 2026-10-17 it held 599 of
the 895 classes of `src/`. The `bare` command is `demo.sh`'s: an image built
before it has the script only, `docker compose run --rm omnibase php
vendor/omniscient/omnibase/docker/bin/bare`.

### The names report: `bare --names`

A second report, **informative, not a guard**: which classes still *name* the
framework, where loading them loads none of it (a type in a signature, a `new`
in a method, an attribute). Nothing is loaded; each file of `src/` is read, its
`use` lines (grouped ones expanded, `use function` and `use const` too) and
its fully qualified names. Two counts: the classes that name the framework
themselves (●), and those that name it through the `Base\` classes they name,
followed to the end (○, with the chain). It is the dashboard of the seams
(C3-C5): the Fondation only grows, this report only falls. `--all` reads every
class of `src/`, a list of classes reads those; `--json` gives the whole
report. On 2026-10-18: of the 679 classes of the Fondation, 131 name the
framework themselves, 417 themselves or through a `Base\` name.

## SQLite

The harness's database is SQLite; the applications run MySQL/MariaDB. Code
that writes SQL by hand says so per platform: the analytics counters
(`Base\Repository\Analytics\*`) upsert with `ON DUPLICATE KEY UPDATE` /
`INSERT IGNORE` on MySQL and `ON CONFLICT` elsewhere
(`VisitRepository::insertIgnore()`), and count distinct pairs over a `SELECT
DISTINCT` subquery rather than MySQL's `COUNT(DISTINCT a, b, c)`.

## Rebuilding the image

The image installs the plugins and the sibling families from Packagist, their
development branches (`glitchr/omnitrade` 1.x with its catalogue,
`omnitrade/stripe`, `glitchr/omnibus` 2.x and two carriers). Docker keeps that
layer until this checkout changes; to fetch the families' latest commits:

```sh
docker compose -f compose.yml -f compose.checkouts.yml build --build-arg OMNI_REFRESH=$(date +%s) omnibase
```

The same goes for the core's own dependencies on their development branches
(`omnibridge/doctrine`, `omnibridge/twig`, `1.x-dev`): an image built before a
commit of theirs that the core needs keeps the older one. Rebuild with
`OMNI_REFRESH` rather than mounting a checkout over `vendor/`; the image of
2026-10-18 has omnibridge/doctrine cf6b3b9 (the UTC types the core extends),
and the suite runs on it without a mount.

`docker/app/` (configuration, `.env.test`, `tests/harness.php`) is copied into
the image: a change there needs a build too.
