---
title: Deployment
order: 30
---

# Deployment

> This is the **base** deployment page. An application that deploys
> differently should override it by creating `docs/30-operations/deployment.md`.

## Generic flow

1. Run the test suite.
2. Build assets.
3. Run migrations.
4. Warm the cache.

## Cache

Warm the cache *before* switching traffic. Several warmers touch the database,
so a cold cache on the first request after a deploy is both slow and risky.

omnibase's `cache:clear` checks the PHP configuration, the disk, webpack's build
and the technical recipient before Symfony's clear, holds a lock (a second
`cache:clear` started meanwhile exits without clearing), and on an installation's
first clear runs a second one in a process of its own (`base.autoclear`). See
[Console commands](../50-reference/commands.md#cacheclear).

## Before switching traffic

- `base.notifier.technical_loopback` is `true` by default: every e-mail goes
  to the technical recipient. Production sets it to `false`.
- `base.site.index` names the home page's route, `base.uploader.storage` and
  `base.twig.editor.storage` the storages of the uploads
  ([Configuration reference](../50-reference/configuration.md)).

## Commands to schedule

| Command | Why |
|---|---|
| `bin/console thread:publishable --publish` | Publishes the scheduled threads whose time has come (and mails their authors) |
| `bin/console trash:purge --force` | Destroys what was trashed longer ago than `base.extension.empty_trash` |
| `bin/console timemachine:snapshot:backup <storage> --batch` | The backups ([Backups](backups.md)) |
| `bin/console demo:reset` | The nightly reset of a demonstration ([The demo environment](../20-architecture/demo.md)) |
