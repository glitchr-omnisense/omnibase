# Changelog

All notable changes to this bundle are documented here, per release tag.
Versions follow the branch-per-major scheme: branch `3.x` → tags `3.0.0`,
`3.1.0`, … (the next major continues on `4.x`).

## [Unreleased]

### Changed
- **The demonstration names its account on every page, and its back office is no longer public** (`docs/20-architecture/demo.md`). The banner (`@Base/demo/_banner.html.twig`) tells a visitor who is not signed in the first account offered, what it signs in with, and links to the sign-in page (`@demo.notice.*`, fr and en): a site that includes the banner has nothing to add. An account whose roles reach `base.demo.admin_roles` (`[ROLE_ADMIN]`, through the hierarchy too) is still created by the fixtures, but in `demo` it is offered nowhere (`DemoAccountRegistry::offered()`, `demo_accounts()`, `/login/demo`) and signs in with `base.demo.admin_password` (the `DEMO_ADMIN_PASSWORD` secret) or not at all - **a site whose demonstration showed its back office at a click sets that secret, or `admin_roles: []` to keep it public**. `Base\Subscriber\DemoSuperAdminSubscriber` is `DemoBackOfficeSubscriber` (both tiers, a secret each).
- **In the demonstration the back office opens to its own accounts alone, and every role has its account.** Any account signed in below `base.demo.admin_roles` - whatever its roles give it elsewhere, a `ROLE_STAFF` reading orders under `/admin` - gets a 403 on every page of the back office (`DemoBackOfficeSubscriber::onRequest()`). `DemoAccountRegistry::missingRoles()` names the roles of the application's `user_role` type below the back office that no account offered holds (`ROLE_USER`: any account); `demo:reset` warns about them, and a site's suite asserts there are none.
- **The cookies are `@glitchr/flowjs/cookie`** (flowjs 1.2.0) in place of `@glitchr/cookie`: `assets/base-async.js` imports it, `package.json` takes `@glitchr/flowjs` `^1.2.0` and drops `@glitchr/cookie`, `yarn.lock` follows (edited by hand: the cookie entry out, the flowjs one at 1.2.0). The `Cookie` global keeps its methods and its `localStorage` keys; it now knows categories of consent, what each stores, and writes nothing under a refused one (omnibase/consent hands it the site's categories and shows the detailed section). `base-async` therefore packs flowjs's core again, and runs before a site's entries: **a site takes `@glitchr/flowjs` 1.2.0 or later too** (the first `Flow` loaded is everyone's), drops `@glitchr/cookie` from its `package.json` and any import of it. Compiled assets rebuilt (only `base-async` changed).
- `@glitchr/flowjs` is 1.2.1 (`package.json` `^1.2.1`, `yarn.lock` edited by hand: only the flowjs entry, registry tarball and integrity). `base-async` packs flowjs's core and runs before a site's entries, and `Flow` and `Cookie` are the first loaded: on every page the cookie module that ran was the core's 1.2.0, and 1.2.1's corrections (`durations`, an expired answer asked again, `bare`, several places for the banner, the countdown waiting while the banner cannot be seen, `Cookie.use()` before its category) reached no site. Compiled assets rebuilt (only `base-async`'s script changed). **A site takes `@glitchr/flowjs` `^1.2.1` too** (front-end.md, scaffold.md).
- **The server writes no cookie the visitor refused.** `Base\Security\CookieAnswer::of($category)` / `::refused($category)` read the answer the page's script keeps (the cookie `cookie-consent` of `@glitchr/flowjs/cookie`: one yes or no per category); `app.site.is_newcomer()` no longer sets `USER/LAST_VISIT` for a visitor who refused `USER`, who is then a newcomer at each page.
- **The time zone is the visitor's to refuse apart.** `assets/styles/js/user.js` writes it in `LOCATION/TIMEZONE` when the site declares a `LOCATION` category for its visitors to answer (omnibase/consent's default), and leaves it in `USER/INFO` (`timezone`) otherwise, as before; it writes again at each answer (`cookie:consent`), so that what is granted is written at once. `User::getCookie('timezone')` reads `LOCATION/TIMEZONE`, else `USER/INFO`'s. `getCookie('country')` is read from the zone at each call and no longer written back into `USER/INFO` by the server (a cookie written behind the visitor's answer). Docs: `docs/40-commons/front-end.md` (Cookies), `docs/20-architecture/time.md`.
- `@glitchr/flowjs` is taken from npm, `^1.0.1` (`package.json`, `yarn.lock`), in place of the git commit of 1.0.0. **The core's bundle no longer imports `@glitchr/flowjs/ajaxer`**: nothing in the core, in the `omnibase/*` bundles or in the sites calls `Ajaxer` but latoucheoriginale (its own controllers), and the import also brought a second copy of flowjs's core (`Flow`) into `base-defer` on top of the one each site packs with its own imports (only one `window.Flow` lives at run time, the first loaded, but the bytes were sent twice). A site that sends named AJAX channels imports `@glitchr/flowjs/ajaxer` in its own `app-defer.js`. `Base.boot()` starts `Sticky.ready()` and `Transparent.ready()` only if their globals are there and the option is not `false` (`sticky: false`, `transparent: false`; the stops need `stops` not `false` and their global): a site that imports the navigation alone starts the navigation alone. Compiled assets rebuilt.
- The front-end libraries `@glitchr/stickyjs`, `@glitchr/transparentjs` and `@glitchr/ajaxer` are one library, `@glitchr/flowjs` 1.0.0 (pinned to its commit until it is on npm): `base-defer.js` imports `@glitchr/flowjs/ajaxer`, `package.json` and `yarn.lock` name flowjs, `assets/boot.js` and the docs speak of it. `Base.boot()` no longer attaches the stops again after each navigation: flowjs measures them again at each page it swaps in, their options kept. The globals and their options are unchanged; compiled assets rebuilt.
- A guarded form shows its captcha only after a few refused tries: `base.guard.captcha_after` (3) and the option `guard.captcha_after`, counted per form and visitor (address, else session) in `cache.app` for 15 minutes, forgotten after a form sent; below, no widget printed and no token asked. `0` keeps the captcha always shown, as before. Below the threshold the two fields (`guard_captcha`, its fallback) are removed from the form's view rather than marked rendered: a template that prints `form_row(form.guard_captcha)` no longer throws "has already been rendered", `form.guard_captcha is defined` is false, and the submission is read as before.
- The forms' guard speaks to `glitchr/omnishield`, the family formerly named `glitchr/omniguard` (renamed on 2026-10-10: the vendor `omniguard` on Packagist belongs to another project): `omnishield/*` in `suggest` and `require-dev`, `Omnishield\` classes, the configuration `omnishield:`, the test token `omnishield-fixed-token`. `FormGuard`, the option `guard` and `base.guard` keep their names.

### Changed
- License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later. `COPYING` and `COPYING.LESSER` removed, `LICENSE` holds the MIT text.

### Added
- The `demo` environment (`docs/20-architecture/demo.md`): demonstration
  accounts declared by the bundles and the application
  (`Base\Demo\DemoAccountProviderInterface`), created by the fixtures
  (`DemoAccountFactory`), offered at a click on the sign-in page
  (`@Base/demo/_accounts.html.twig`, a POST with a CSRF token); the banner
  (`@Base/demo/_banner.html.twig`); `demo:reset` under `symfony/lock`; no
  e-mail sent, `X-Robots-Tag: noindex`, credentials of a demonstration
  account locked, the staff's mandatory second factor lifted for it, the
  super-administrator signed in by a secret or not at all, and a kernel that
  refuses to start in debug or on the production database. Nothing of it is
  registered in any other environment.
- Standalone Docker demo (root `Dockerfile` + `example/app/`): bare Symfony
  skeleton + this bundle + SQLite, one flat page touring the App\ ↔ Base\
  mirroring, entity layer, setting bag, obfuscator and translator. The
  `example/app/` overlay doubles as a minimal host-app reference config.

### Fixed
- **A page, an attachment or a short link that does not exist answers 404**, the site's error page: `WidgetController` (`/page/{slug}`, `widget_page`; `/attachment/{slug}`, `widget_attachment`) and `ShortLinkController` threw php-http's `Http\Discovery\Exception\NotFoundException`, no HTTP exception, and every unknown address under them answered 500 (a site's link to `/page/gdpr`, a page it never made). They throw `createNotFoundException()`. Tests: `tests/Http/WidgetPageHttpTest.php`, `ProdEnvironmentHttpTest::testAnUnknownPageAnswers404()`.
- **A picker searches an entity whose repository is a plain Doctrine one.** The autocompletion (`/ux/autocomplete/{data}`) asked the entity's repository for `cacheByInstanceOf()`, which only omnibase's `Base\Database\Repository\ServiceEntityRepository` answers: on a bundle's DoctrineBundle `ServiceEntityRepository`, or the `EntityRepository` an entity inheriting another's mapping gets when it names none (omnibase/classroom's levels, subjects and purposes, sequences, resources), every search answered 500 and the picker stayed on "Searching...". `AutocompleteController::repository()` builds one of omnibase's for that class (the controller is given `doctrine`); `ServiceEntityParser` reads the class a repository was built for (`getClassName()`), no longer the one its own name implies (`getFqcnEntityName()`, unchanged for every repository that names none). Test: `tests/Controller/UX/AutocompletePlainRepositoryTest.php`.
- **A picker's search takes a space.** `SelectType` gave select2 its separators (a space, a comma, a semicolon) whether the select takes tags or not; without tags, select2's tokenizer threw at the first separator typed (`_removeOldTags is not a function`) and the search stopped: "Cours élémentaire" found nothing. The separators go to a select of tags only. Test: `tests/Field/SelectTypeTokenSeparatorsTest.php`.
- The icon of a taxon, a tag, a permission and an attribute adapter (`AdapterCrudController`) is optional in the back office, as its column: a taxon without one (omnibase/classroom's levels and subjects, a blog's topics) could not be saved again as it was ("This value should not be blank" on Icon). Test: `tests/Controller/OptionalIconTest.php`.
- **A taxon is made with the label of the site's language alone.** `TaxonCrudController` asked the label in every language (`'label' => ['required' => true]` on each translation), so no level, subject, FAQ section or product category could be saved with one label. The label is asked in the default language only (`'required' => fn (bool $required, string $locale, array $options): bool => $required`, the form of `TranslationType`'s closure; an empty translation is dropped), as an attribute adapter's (`AdapterCrudController`). The slug follows that label as it is typed (`setTargetFieldName('translations.label')`). A taxon without any label is refused on the server too (`Taxon::validateLabel()`, "This value should not be blank." on its translations): only the browser's `required` stopped it, and it was saved with no name. Test: `tests/Controller/TaxonLabelTest.php`.
- **The fields that name no label are translated.** A field without a label shows its property humanised ("createdAt" -> "Created at"), translated through the `forms` domain by the form, the list's columns and its filters; "State" and "Slug" were in no language, so every `StateField` and `SlugField` of every bundle read English, as did the other fields and filters of omnibase's own screens (Icon, Created at, Locked, Roles, Plain password...). `forms+intl-icu.{en,fr}.yaml` carry them, and the names the bundles' screens share (Status, Title, Published at, Featured, Visible...): a site's own catalogue still prevails. Test: `tests/Translation/FieldLabelsCatalogueTest.php` (every label omnibase's screens leave to their property, in each language of the domain).
- `composer.json` now declares dependencies the bundle's DI actually
  hard-references: `hwi/oauth-bundle`, `symfony/webpack-encore-bundle`,
  `symfony/monolog-bundle`, `glitchr/ux-google`, plus `ext-imagick` /
  `ext-igbinary` (required unconditionally in `BaseBundle::boot()`).

## [3.0.0] — 2026-07-04

First tagged release of the 3.0 line (branched from 2.0 in 2025).

### Added
- Offline mode: YouTube-style offline page takeover.
- Turbo enabled across admin; admin UX fixes.

### Changed
- Upgraded to Symfony 8.0 (from 6.x/7.x line); EasyAdmin 5 compatibility.
- Asset pipeline optimized (async/defer entrypoints).

### Fixed
- Stale `dirty-form` `onbeforeunload` handler lingering across Turbo visits
  in the admin (spurious "Leave site?" dialogs).
- CSP: removed `csp_nonce('script')` from admin layout and CRUD templates.
- SF8 compatibility: replaced removed `Request::get()` usages.
- EasyAdmin CRUD form deprecations, admin context factory/override updates.

### Repository
- License texts added (`COPYING`, `COPYING.LESSER` — LGPL-3.0-or-later).
- `.php-cs-fixer.cache` untracked; `.gitignore` cleaned up.
- Tests bootstrap (PHPUnit 10) and GitLab CI (php-cs-fixer, PHPStan, PHPUnit).
- History slimmed: built assets under `public/` removed from past revisions.

## [1.3] — 2023-08-19 · [1.2] — 2023-06-27 · [1.1] — 2023-04-24 · [1.0] — 2023-04-03

Historical tags of the 1.0 line (Symfony 6). See `git log 1.0..1.3` for details.
