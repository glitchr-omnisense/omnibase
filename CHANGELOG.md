# Changelog

All notable changes to this bundle are documented here, per release tag.
Versions follow the branch-per-major scheme: branch `3.0` → tags `3.0.x`.

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
