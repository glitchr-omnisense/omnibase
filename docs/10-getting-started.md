---
title: Getting started
order: 10
---

# Getting started

## Requirements

What `composer.json` requires:

| Component | Version |
|---|---|
| PHP | 8.4 or later, with `bcmath`, `exif`, `igbinary` and `imagick` |
| Doctrine | ORM 3.4+, DBAL 4.2+, Persistence 4 |
| Twig | 3 |
| Symfony's standalone components | 6, 7 or 8 (cache, mailer, notifier, serializer, translation, validator, workflow...) |
| omnisense/omnibridge, omnisense/omnidrive, omnibridge/composer, omnibridge/doctrine, omnibridge/twig | 1.x |

glitchr/backup-manager (the TimeMachine, the backups) is suggested, not required:
see below.

The framework itself is not required by the core (since 4.0, see
[What the core requires](20-architecture/packages.md)): under Symfony it comes with
**omnibridge/symfony** (FrameworkBundle, SecurityBundle, TwigBundle,
DoctrineBundle, MonologBundle, Messenger, Form, 6.1 to 8). The sites run MySQL 8;
the core's harness runs on SQLite. The commands `doctrine:schema:charset` and
`doctrine:array:upgrade`, and the backups, are MySQL's.

## Installation in a Symfony application

```bash
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase:5.x-dev omnibridge/symfony:1.x-dev omnibridge/doctrine:1.x-dev omnibridge/twig:1.x-dev
```

Since 5.0 the core is a library that requires neither Symfony's framework nor Doctrine nor Twig
([What the core requires](20-architecture/packages.md)): omnibridge/symfony is the bundle and the
framework, omnibridge/doctrine the persistence, omnibridge/twig Twig's helpers. A feature that needs
a package the core only suggests (symfony/http-client, phpoffice/phpspreadsheet,
web-auth/webauthn-lib, florianv/swap, league/flysystem-ftp, symfony/discord-notifier, the Twig
extras) asks the application to require it. Upgrading from 4.x: [UPGRADE-5.0.md](../UPGRADE-5.0.md).

`omnibridge/composer` is a Composer plugin (it applies the core's patch to
symfony/process): Composer asks before it runs one, hence the first line.
omnibridge/doctrine and omnibridge/twig are the application's to require since 5.0
(4.x required omnibridge/doctrine).

The backups ([Backups](30-operations/backups.md), the `timemachine:*` commands)
need **glitchr/backup-manager**, a suggestion of the core since 4.0: a site that
backs up through the TimeMachine requires it at its root.

```bash
composer require glitchr/backup-manager:4.0.*-dev
```

Without it the TimeMachine and its commands are not registered, and
`base.time_machine` is read by nothing.

Register the bundle in `config/bundles.php`, after the bundles it works with:

```php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    // ...
    // omnibase's bundle, omnibridge/symfony's since 5.0 (Base\BaseBundle in 4.x)
    Omnibridge\Symfony\Omnibase\BaseBundle::class => ['all' => true],
];
```

Give it its configuration, `config/packages/base.yaml` - the smallest one is in
the [Configuration reference](50-reference/configuration.md#the-smallest-configuration) -
and import its routes ([Routes and access](50-reference/routes-and-access.md#importing-the-routes)).
Then the schema and the front end:

```bash
php bin/console doctrine:migrations:migrate
npm install && npm run dev
php bin/console cache:clear
```

A new site is better started from an existing one: [Scaffolding a site](scaffold.md)
says what to copy and in which order to start it. The core's harness
([The Docker harness](30-operations/harness.md)) runs the core with every
`omnibase/*` plugin, without a site.

## Conventions

The `Base\` namespace mirrors `App\`: a class in `Base\Entity\User` is
aliased so the application can override it at `App\Entity\User` without the
bundle knowing. This is deliberate and load-bearing — do not rename it.
`bin/console base:mapping` lists the aliases ([Console commands](50-reference/commands.md#basemapping)).
