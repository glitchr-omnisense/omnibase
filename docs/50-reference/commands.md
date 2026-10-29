---
title: Console commands
order: 20
---

# Console commands

The commands the core registers (`config/services/core.php`, `config/services/demo.php`,
`services-decoration.php`), as `bin/console list` shows them in the core's
harness. Most extend `Base\Console\Command`, which needs a `ConsoleOutput` (it
throws `LogicException` on any other output, a `BufferedOutput` included) and
adds a `--purpose` option to some of them; `user:create` is a plain Symfony
command, usable from scripts and tests.

The usages below are `bin/console help <name>` in the harness, Symfony's common
options (`-h`, `-q`, `-v`, `-e`, `--no-debug`...) left out.

## Summary

| Command | What it does |
|---|---|
| `cache:clear` | Symfony's `cache:clear`, decorated: checks around it, a lock, a second clear |
| `cache:clear:sessions` | Deletes the session files of `var/sessions` |
| `user:create` | Creates an account, verified and approved, with a temporary password |
| `user:notifications` | Counts, shows and erases the notifications read |
| `notifier:mail:test` | Sends the test e-mail |
| `app:notifier` | Lists the notifications the `Notifier` can send |
| `base:mailer:trap` | Holds outgoing mail, then purges or releases it |
| `thread:publishable` | Publishes the scheduled threads whose time has come |
| `trash:purge` | Destroys the trashed entities past their grace period |
| `timemachine:snapshot` | Lists the backups' connections, storages and snapshots |
| `timemachine:snapshot:backup` | Makes a backup |
| `timemachine:snapshot:restore` | Restores a backup |
| `uploader:entities` | Lists the files of the `#[Uploader]` properties, finds and deletes orphans |
| `uploader:images` | The same, and warms up the images' cache |
| `uploader:images:crop` | The same for the image crops |
| `icon:entities` | Lists the icons of the entities that have some |
| `translation:controllers` | Lists the routes' titles in each locale |
| `translation:settings` | Lists the settings' values in each locale |
| `entity:discriminator` | Checks that a class is an entity |
| `base:mapping` | Lists the `Base\` classes aliased to `App\` |
| `doctrine:database:import` | Imports rows from a spreadsheet |
| `doctrine:schema:charset` | Shows or changes the database's and the tables' character set (MySQL) |
| `doctrine:array:upgrade` | Converts DBAL 3's serialized `array` columns to JSON (MySQL) |
| `demo:reset` | Reloads the demonstration ([The demo environment](../20-architecture/demo.md)) |

Classes of `src/Console/Command/` that no configuration registers, so that
`bin/console` does not know them: `AutocompleteEntitiesCommand`
(`autocomplete:entities`), `IconControllersCommand`, `IconCrudCommand`,
`IconEnumsCommand`, `TimeMachineStorageCommand`, `TranslationCrudCommand`,
`TranslationEntitiesCommand`, `TranslationEnumsCommand`.

## `cache:clear`

```
Usage:
  cache:clear [options]

Options:
      --sessions             Kill all existing sessions
      --no-extension         Skip base extension
      --no-warmup            Do not warm up the cache
      --no-optional-warmers  Skip optional cache warmers (faster)
```

`Base\Console\Command\CacheClearCommand` runs Symfony's own command
(`console.command.cache_clear`) between omnibase's steps:

1. It takes a lock, `var/cache/<env>/.lock` (a lock file older than 120 seconds
   is taken as stale and removed). When another `cache:clear` holds it, it prints
   "Cache clear aborted: another process is already clearing the cache." and
   exits 0.
2. Unless `--no-extension`: the PHP configuration, disk and memory, the cache, the
   virtualisation and the PHP extensions are checked and reported, then webpack's
   build, the technical recipient (`base.notifier.technical_recipient`), a
   `phpinfo` page and the public symlinks are made or checked - all before the
   cache goes, while the services they read can still be built.
3. Symfony's `cache:clear` runs, with the same options.
4. A counter of clears is kept in `var/cache/<env>.txt`; `--sessions` then runs
   `cache:clear:sessions`.
5. Unless `--no-extension`: on the first clear of an installation and with
   `base.autoclear`, `cache:clear` runs a second time in a process of its own;
   then OPcache is reset.

## `cache:clear:sessions`

```
Usage:
  cache:clear:sessions [options]

Options:
      --purpose[=PURPOSE]  Show command description ?
```

Deletes every `sess_*` file of `var/sessions`, which signs everybody out where
the sessions are kept in files there.

```
$ php bin/console cache:clear:sessions
 [INFO] Session files cleared from /srv/demo/var/sessions.
```

## `user:create`

```
Usage:
  user:create [options] [--] <username> <email>

Arguments:
  username                 Its username
  email                    Its e-mail address

Options:
  -r, --role=ROLE          Its role(s): USER, ADMIN, SUPERADMIN... [default: ["USER"]] (multiple values allowed)
  -p, --password=PASSWORD  Its password (otherwise generated)
      --keep-password      Do not ask for a new password at the first sign-in
      --no-email           Do not e-mail the account its username and password
```

The account is verified and approved. A role is a key of `UserRole`, with or
without `ROLE_` (`-r ADMIN -r EDITOR`); an unknown one fails the command. Without
`--password`, the password is four groups of four letters and digits, without
the characters read alike (0/O, 1/l/I), printed once. Without `--keep-password`
the account gets a `change-password` token, and `PasswordChangeSubscriber` asks
for a new password at its first sign-in. Without `--no-email`,
`Notifier::sendAccountCreated()` mails it its username and password. The
username is written only where the application's `User` has a `username`
property (`Base\Entity\User` has none); an e-mail address or a username already
taken fails the command.

```
$ php bin/console user:create jdoe jdoe@example.com --no-email

 [OK] jdoe (jdoe@example.com), ROLE_USER.

Password: a56J-S2gs-m7Lj-CLQj - to be changed at the first sign-in
```

## `user:notifications`

```
Usage:
  user:notifications [options]

Options:
      --user=USER      The account (its identifier); every account without it
      --expiry=EXPIRY  The age past which a read notification is erased ("+30 days"); base.user.notifications.expiry without it
      --show           List them
      --clear          Erase the read ones older than the expiry
      --clear-all      Erase every read one, whatever its age
```

Counts every notification (the account's with `--user`), those read, and those
read and older than the expiry (`--expiry`, else `base.user.notifications.expiry`,
`+30 days`); `--show` lists them, `--clear` erases the read ones past the expiry,
`--clear-all` every read one. An unread notification is never erased.

```
$ bin/console user:notifications --user=anne@example.org --clear
3 notification(s) found, 1 notification(s) are marked as read, 1 notification(s) older than +30 days
These notifications are now erased..
```

## `notifier:mail:test`

```
Usage:
  notifier:mail:test [options]

Options:
      --user[=USER]     User ID (or email, or username) to send the notification to
```

Sends `Notifier::testEmail()` to the user named, else to the user whose address
is the technical recipient's, else to an unsaved user with that address. With
`base.notifier.technical_loopback` on, it says so: the e-mail goes to the
technical recipient whoever it is for.

```
$ php bin/console notifier:mail:test

 ! [NOTE] Notifier loopback is enabled. Notifications will be sent to the
 !        technical recipient:
 !        "admin@example.org <admin@example.org>" / en-GB

 [WARNING] No user found in database as technical recipient: "admin@example.org
           <admin@example.org>" / en-GB

 [OK] Sent test email to admin@example.org (Name: admin@example.org, ID: 0)
      using async transport.
```

## `app:notifier`

Lists the public methods of `Base\Notifier\Notifier` that `BaseNotifier` does not
declare: the notifications it can build.

```
$ php bin/console app:notifier
Available templated notifications:
 * getContactRecipient
 * testEmail
 * contactEmail
 * contactEmailConfirmation
 * userAccountGoodbye
 * userApprovalRequest
 * userApprovalConfirmation
 * accountCreated
 * resetPasswordRequest
 * verificationEmail
 * loginToken
 * loginLink
 * emailCode
 * userWelcomeBack
 * words
 * duration
```

## `base:mailer:trap`

```
Usage:
  base:mailer:trap [options] [--] [<action> [<id>]]

Arguments:
  action                status, on, off, list, show, purge, release [default: "status"]
  id                    Which held message, for show/purge/release

Options:
      --all             Apply to every held message
      --reason=REASON   Note why the trap was armed, for whoever finds it later
```

Holds the outgoing mail during an incident. Armed (`on`, or `arm`), the trap
(`Base\Mailer\MailTrap`, listening to symfony/mailer's `MessageEvent` at priority
-1000) keeps every message in `var/mail-trap/` instead of sending it - at send
time, so mail already queued for a worker is caught too.

| Action | Effect |
|---|---|
| `status` | Armed or not (since when, why), the number of messages held, the directory; a note when messages are held while the trap is off |
| `on` / `arm` | Arms it; `--reason` is kept with it |
| `off` / `disarm` | Disarms it. The messages held stay held: `release` or `purge` them |
| `list` | A table: id, captured at, to, subject |
| `show <id>` | One message in full |
| `purge <id>` / `--all` | Deletes them, never sent |
| `release <id>` / `--all` | Sends them on the real transport chain (`mailer.transports`), past the trap even while it is armed, then forgets them |

```
$ php bin/console base:mailer:trap status

 [OK] Trap off - mail is being delivered normally.

 Held messages: 0
 Directory:     /srv/demo/var/mail-trap
```

## `thread:publishable`

```
Usage:
  thread:publishable [options]

Options:
      --publish         Should I publish them ?
```

Reads the threads in the `FUTURE` state (`Threads::byStateUncached()`), says
which are publishable (their publication time has come), and with `--publish`
publishes each: its row locked (`PESSIMISTIC_WRITE`) and read again, so that two
runs at once publish it once, then `poke()`d and flushed, which raises
`thread.publishable` (see [Events](events.md)). With `-v` each thread is listed.
Meant for a cron.

```
$ php bin/console thread:publishable

 [OK] 0 scheduled thread(s) found: 0 thread(s) publishable.
```

## `trash:purge`

```
Usage:
  trash:purge [options]

Options:
      --force           Actually destroy them (without this, only reports)
      --limit=LIMIT     Destroy at most N entries in this run
```

Destroys the trashed entities (`TrashBall`) past their grace period
(`base.extension.empty_trash`). A dry run unless `--force`: it lists what it
would destroy. Meant for a cron.

```
$ php bin/console trash:purge
Nothing to purge. The trash holds 0 entries, none past its grace period.
```

## `timemachine:snapshot`, `:backup`, `:restore`

```
Usage:
  timemachine:snapshot [options] [--] [<storages>...]

Arguments:
  storages                   What storages do you want to backup?

Options:
      --cycle[=CYCLE]        Which version do you want to get?
      --prefix[=PREFIX]      Which prefix do you want to use? [default: "dev"]
      --database[=DATABASE]  Which database do you want to backup?
```

The prefix's default is the kernel's environment (`dev` in the harness).
`:backup` adds `--id`, `--batch` (no confirmation asked) and `--userlog` (the
user configuration included); `:restore` adds `--id`.

`timemachine:snapshot` lists the database connections, the storages of the
backups (Flysystem storages, remote ones marked) and, with `-v`, the snapshots by
day. `:backup` asks for confirmation (none with `--batch`), says whether the
user configuration is included (`--userlog`), and fails without a storage named.
`:restore` asks whether to restore the latest version (when no `--id`), then
whether to restore the application from its tarball and the database. See
[Backups](../30-operations/backups.md) and `base.time_machine` in the
[configuration](configuration.md#time_machine).

> [!NOTE]
> In the harness (a SQLite connection) `timemachine:snapshot` stops on "Could not find field
> `serverVersion` in configuration for connection type `default`": the backups read the
> connection's parameters of a MySQL server.

## `uploader:entities`, `uploader:images`, `uploader:images:crop`

```
Usage:
  uploader:entities [options]

Options:
      --entity[=ENTITY]      Should I consider only a specific entity ?
      --property[=PROPERTY]  Should I consider only a specific property ?
      --uuid[=UUID]          Should I consider a specific uuid ?
      --orphans              Do you want to get orphans ?
      --delete-orphans       Do you want to delete orphans ?
```

`uploader:entities` reads every `#[Uploader]` property of the entities
(`App\Entity\...` and `Base\Entity\...`, or the one `--entity` names), counts their
files and, with `--orphans`, finds the orphans: files of the storage no row
names, and names in rows with no file. `--delete-orphans` deletes them from the
storage. A property declared `missable` is passed by (it cannot have orphans).
`-vv` lists the orphans, `-vvv` every file. `demo:reset` runs it with
`--delete-orphans` (`base.demo.reset.orphans`).

`uploader:images` adds `--warmup` (build the cached images: each file, as is and
in WebP, and its thumbnails at the sizes of `base.uploader.formats`), `--format`
(only one `WIDTHxHEIGHT`), `--cache` and `--batch` (both flags, they take no
value). `uploader:images:crop` adds `--normalize` and `--max-definition`, and
warms up the crops of the `ImageCrop` entities (each crop of each image's
`source`, WebP and not).

## `icon:entities`

```
Usage:
  icon:entities [options]

Options:
      --id[=ID]          Should I pick up one specific entityId ?
      --entity[=ENTITY]  Should I consider only a specific entity ?
```

Without `--entity`, each entity class implementing `IconizeInterface` (the
core's and the application's) and its static icons; with `--entity`, the icons
of each row (or the one `--id` names).

```
$ php bin/console icon:entities
Entity list:
 * Base\Entity\Extension\Abstract\AbstractExtension: [fa-solid fa-external-link]
 * Base\Entity\Extension\Revision: [fa-solid fa-sort-numeric-down]
 * Base\Entity\Extension\TrashBall: [fa-solid fa-trash-alt]
 * Base\Entity\Layout\ShortLink: [fa-solid fa-compress-alt fa-rotate-45]
 ...
```

## `translation:controllers` and `translation:settings`

```
Usage:
  translation:controllers [options]

Options:
      --controller[=CONTROLLER]  Should I consider only a specific controller ?
      --suffix[=SUFFIX]          Should I add a specific translation suffix to the default path ? [default: "title"]
      --locale[=LOCALE]          Should I display only a specific locale ?

Usage:
  translation:settings [options]

Options:
      --path[=PATH]      Should I consider only a specific setting path ?
      --raw              Should I display the raw information ? (including user label and help information)
      --locale[=LOCALE]  Should I display only a specific locale ?
```

`translation:controllers` prints, for each route whose controller is in `App\`
or `Base\` (or starts with `--controller`), the text of
`@controllers.<route>.<suffix>` in each available locale, "no translation found"
where the catalogue has none. `translation:settings` prints the settings (those
under `--path`) in each locale, `--raw` their labels and help texts. A
`--locale` outside the available locales is an error.

## `entity:discriminator`

```
Usage:
  entity:discriminator <entity>
```

Throws "Class "..." is not an entity." when the class is not a mapped entity;
otherwise prints nothing and exits 0.

## `base:mapping`

Lists the classes of the core's `Enum`, `Form`, `Entity` and `Repository`
directories, each "aliased to" its `App\` name when the application has no class
of that name, or overridden by the application's.

## `doctrine:database:import`

```
Usage:
  doctrine:database:import [options] [--] [<path>]

Arguments:
  path                             Path or URL

Options:
      --spreadsheet[=SPREADSHEET]  Import selected spreadsheet (e.g. 1,3,4 [NB: starts from 1])
      --extension[=EXTENSION]      Specify file extension to be used
      --entries[=ENTRIES]          Only read N-rows
      --skip[=SKIP]                Skip i-entries [default: 0]
      --batch[=BATCH]              Persist by batch of X entries. [default: 25]
      --notify                     Send notification if needed
      --force                      Import without asking confirmation.
      --on-the-fly                 Import right after each single entity got hydrated
```

Reads a workbook (`xls`, `xlsx`, `xlsm`, or `xml`, from the file's MIME type or
`--extension`; the path is asked when not given), lets you choose its sheets
(or takes `--spreadsheet`), and imports each sheet as entities of the class its
first column's heading names. The second line of a sheet is a comment line,
skipped. A heading is a property path; `name:unique` makes the column a key
(a row already in the database is not imported again), `tags:explode(,)` splits
a cell into a list, `parent[title]` finds an associated entity by one of its
fields. The notifier is disabled unless `--notify`.

> [!WARNING]
> Known defect: a `csv` or `txt` file always fails with "File extension couldn't be determine..
> Please use "--extension" option.": its `case` decodes it and falls through to the `default`
> that throws (src/Console/Command/DoctrineDatabaseImportCommand.php, the `// no break`).

## `doctrine:schema:charset`

```
Usage:
  doctrine:schema:charset [options]

Options:
      --update                         Update database charset,collate
      --charset[=CHARSET]              Database charset value
      --collate[=COLLATE]              Database collate value
      --update-tables                  Update table collate
      --table[=TABLE]                  Table name
      --table-collate[=TABLE-COLLATE]  Table collate value
```

MySQL only (`SHOW VARIABLES`, `SHOW TABLE STATUS`, `ALTER DATABASE`). Without
options it shows the database's character set and collation and each table's
collation. `--update` alters the database to `--charset`/`--collate` (else the
connection's `charset` and `defaultTableOptions.collate`); `--update-tables`
converts every table (or `--table`) to them. `--table-collate` only appears in
the message printed: the tables are converted to `--collate`.

## `doctrine:array:upgrade`

MySQL only. Finds the columns whose comment says `(DC2Type:array)` (DBAL 3's
serialized arrays) or that are JSON, rewrites each row's serialized value as JSON
(an empty value becomes `[]`, a value already JSON is left, a value that does not
unserialize is skipped and reported), then alters an `array` column to `JSON`.
Errors are printed, not thrown.
