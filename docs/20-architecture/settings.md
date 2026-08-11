---
title: Settings
order: 10
---

# Settings

Settings are stored per locale and compiled into a single snapshot at warmup,
so reading one at runtime never queries the database.

## Reading a setting

```php
$title = $settingBag->get('base.settings.title');
```

`get()` degrades to `null` on a missing path rather than throwing — a missing
setting is a normal state during a deployment, not an error.

## Translatable values

A setting's value lives in `SettingIntl`, one row per locale. The default
locale is not special-cased at the storage layer; every locale is a row.
