---
title: Settings
order: 10
---

# Settings

Settings are stored per locale and compiled into a single snapshot at warmup,
so reading one at runtime never queries the database. They are what the back
office edits (`base.settings.title`, `base.settings.maintenance`,
`api.spam.akismet`...), read through `Base\Service\SettingBag`
(`Base\Service\SettingBagInterface`).

`base.parameter_bag.use_setting_bag` must be on: off, the snapshot is empty and
every setting reads as unset ([Configuration reference](../50-reference/configuration.md#parameter_bag)).

## Reading a setting

```php
$title = $settingBag->getScalar('base.settings.title');          // 'My site', or null
$title = $settingBag->getScalar('base.settings.title', 'fr-FR'); // in that locale
$tree  = $settingBag->get('base.settings');                      // the values under the path
```

`get()` answers an array: the path's own value under `_self`, the values of the
paths under it by their names (`get('base.settings.title')` is
`['_self' => 'My site']`). `getScalar()` is `get($path, $locale)['_self'] ?? null`.
Both take a list of paths too. A value is read in the locale asked, else the
default locale, else the first locale it has.

A missing setting degrades to `null` rather than throwing - a missing setting is
a normal state during a deployment, not an error. A malformed path (`_self` in
the middle of it) still throws.

In a template, `app.settings` is `get('app.settings')`, and the setting
`base.settings.maintenance` puts the site under maintenance.

## Writing a setting

```php
$settingBag->set('base.settings.title', 'My site', 'en-GB');
$settingBag->set('base.settings.title', null, 'en-GB'); // removes that locale's value
```

`set()` creates the setting when it does not exist, refuses a locked one
(`lock()`, `unlock()`), flushes, evicts the second-level cache and invalidates
the snapshot. `setLabel()` and `setHelp()` give the back office's label and
help text, `secure()` marks a setting as secret. `has()`, `remove()`.

## Translatable values

A setting's value lives in `SettingIntl`, one row per locale. The default
locale is not special-cased at the storage layer; every locale is a row.

## A setting over a parameter

`setBag($path, $parameterName)` links a setting to a parameter of the
configuration: with `base.parameter_bag.use_hot_bag` on, the setting's value (in
the default locale) answers for that parameter before `base.yaml`'s
(`getBagParameters()`, laid over the parameters by `HotParameters` on each
request and command). This is how the back office changes an option of the
configuration without a deploy.
