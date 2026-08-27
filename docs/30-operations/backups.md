---
title: Backups
order: 40
---

# Backups

`timemachine:snapshot:backup` builds one archive of the application (its code,
its uploaded data and a database dump) and transfers it to one or more
configured storages.

```bash
php bin/console timemachine:snapshot:backup sftp.glitchr --batch --database default
```

The positional argument names the **destination** storages, not the sources -
every storage listed in `config/packages/flysystem.yaml` may be used, and the
snapshot is written to each of them in turn.

## What ends up in the archive

The tarball deliberately leaves out anything a deployment can rebuild by
itself. On a typical application `vendor/` and `node_modules/` alone account
for roughly three quarters of the tree, and none of it is data you could not
reproduce with `composer install` and `yarn`.

The default exclusion list is `Base\Service\TimeMachine::DEFAULT_EXCLUDES`:

```
./vendor  ./node_modules  ./var/cache  ./var/log  ./var/coverage  ./var/phpunit  ./.git
```

Override it per application when the defaults do not fit:

```yaml
# config/packages/base.yaml
base:
    time_machine:
        excludes:
            - ./vendor
            - ./node_modules
            - ./var/cache
            - ./public/build
```

> Restoring a snapshot built with these defaults therefore needs
> `composer install` (and a front-end build) before the application boots.
> Everything that is *not* reproducible - uploads, the database dump - is
> always archived.

## Where the archive is staged

The snapshot is assembled on local disk before being transferred, by default
under the kernel cache directory. On a host where the application shares a
filesystem with everything else, a large snapshot can therefore fill the disk
the application itself is running on.

Point the staging directory at another volume when that is a risk:

```yaml
base:
    time_machine:
        snapshot_dir: /mnt/storage/snapshots
```

Before building anything, the command measures the source (with the exclusions
applied) and refuses to start when the target filesystem cannot hold roughly
twice that - the tarball and its compressed copy exist side by side while
compressing:

```
Not enough free space to build the snapshot in "/srv/app/var/cache/prod":
~2.6GB needed (source ~1.2GB x2.2), only 900MB available.
```

## Retention

Snapshots older than `time_machine.time_limit` (default `+30 days`) are
**deleted from the destination storage** at the start of every run, and
`max_cycle` bounds how many same-day cycles are kept. A destination is
therefore not an archive of record: it is a rotating window. Keep anything
that must outlive that window somewhere the command does not prune.
