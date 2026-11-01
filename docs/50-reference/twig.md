---
title: Twig reference
order: 40
---

# Twig reference

The globals, functions, filters and tests the core's Twig extensions add, as
the harness's `twig` environment lists them (`Base\Twig\Extension\*`, the plugins'
left out). Where two extensions give the same name, the one registered last wins:
`get_class` is PHP's (`ClassTwigExtension`), `count` and `round` are
`MathTwigExtension`'s, `trans` is omnibase's translator's.

## Optional, over neutral helpers

Twig is the application's choice, not the core's (plan "omnibase agnostic", step F4).
`twig/twig`, its extras and omnibridge/twig are suggestions of the core: where Twig is
installed, `config/services/twig.php` registers everything on this page (and
services-public.php, services-decoration.php, services-fix.php their Twig part); where it
is not, nothing of `src/Twig` is registered or loaded, and the rest of the core loads and
works the same (`BareTest::testTwigIsOptional()`, on the vendor without Twig). No class
outside `src/Twig` names `Base\Twig\` but the three Symfony host classes of the layer
(`TwigSubscriber`, `WebpackCacheWarmer`, `FormTypeWebpackExtension`, which leave for
omnibridge/symfony at F5c): `tests/View/TwigIsOptionalTest.php` is the guard.

The logic of the functions and filters is in neutral helpers, `Base\View\*`, which name
no Twig type; each extension is a thin wrapper over them, under the same names, so no
template changes. A Blade view (omnibridge/laravel) or a React page's props call the same
helpers:

| Helper | What it gives | The Twig names over it |
|---|---|---|
| `Formats` (static) | highlight, mailto, mailparse, truncate, pickup, colour names, `preg_split`, pad, short class names, static calls and properties, property paths | `highlight`, `mailto`, `mailparse_email`, `mailparse_name`, `pickup`, `color_name`, `preg_split`, `pad`, `transforms`, `nargs`, `instanceof`, `is_linkable`, `method_exists`, `static_call`, `static_property`, `property_accessor`, `greater_than`, `get_short_class` |
| `Captions` | a controller's title and excerpt, a value as text | `title()`, `excerpt()`, `stringify` |
| `Dates` | a moment as a pattern says it, by PHP's intl as twig/intl-extra formats it | `datetime` (in Twig's time zone) |
| `Money` | an amount in the visitor's currency, by PHP's intl as twig/intl-extra formats it | `format_currency`, `apply_currency_rate` |
| `Pagination` | a pagination's links, and what its template is given | `paginator()`, `paginator_*()` |
| `Links` | a link to an address or a linkable record, a record's address | `urlify`, `linkify` |
| `Enums` | an enumeration by class or type name, its colours, a value's words | `enum()`, `colorify`, `trans_enum`, `trans_enumExists` |
| `Menus` | the language, theme (modes, filters, widths), time zone and country menus as data | `render_locale()`, `render_theme_*()`, `render_timezone()` (Twig renders the dropdowns) |
| `Bell` | the unread count, the latest notifications, the token, push | `notifications_*()`, `push_available()`, `push_vapid_key()`, `notification_admin_url()` |
| `Pictures` | a stored picture's address, crop and thumbnails in a view's context (an e-mail's) | `image()`, `crop`, `thumbnail*` |
| `Commons` | embeds, QR codes, opening hours, a stored picture's URL (the screens' Pictures contract) | `embed_url()`, `qr_code()`, `opening_hours()`, `picture` |
| `Wysiwyg` | a stored text rendered, its headings (the screens' Texts contract) | `wysiwyg`, `wysiwyg_toc` (through `HtmlTwigExtension`) |
| `Demo` | the demonstration's mode and accounts | `demo_mode()`, `demo_accounts()` |
| `DeployVersion` | the deploy's version | `deploy_version()` |
| `Widgets` | a slot's widget, its template and options | `render_slot()` (Twig renders it) |
| `AssetPaths` (contract) | an asset's path as the page links it | omnibase's `Base\Twig\Environment` answers it |

Twig-only by nature, as they are Twig: what renders a template the core ships (`crudify`,
`htmlify`, `countdown`, `progress`, `clipboard()`, `render_breadcrumb()`, `share`), what reads
Twig's loader or environment (`embed`, `inline_css_email`, `call_user_func_if_exists()`), the
Symfony form's (`form_proxy()`, `form_view()`), the tag renderers' (`html_entry_*()`,
`encore_entry_*()`), the globals (`app`, `security_policy`) and the variables. The services that
already were the logic (the translator's `trans*`, the Localizer's and the Themizer's filters,
`iconify`, `semantify`, `local_business()`, `analytics_*()`, the Texts contract) are called
directly by their extensions, as before.

## Globals

| Global | Value |
|---|---|
| `app` | `Base\Twig\AppVariable`: Symfony's `app` (`app.request`, `app.user`, `app.session`, `app.flashes`...) behind a proxy, and `app.bag(key)` (a parameter by its dotted path, or the bag itself), `app.settings` (the setting `app.settings`), `app.referrer`, `app.locale` (`_self`, `lang`, `country`), `app.theme` (the `Themizer`), `app.site`, `app.email`, `app.admin`, `app.random` |
| `security_policy` | `Base\Service\SecurityPolicy`: the administrator's account-security policy, for the sign-in page and the layout (whether passkeys are offered) |

`app.site` (`Base\Twig\Variable\SiteVariable`) gives `title()`, `slogan()`,
`logo()`, `index()`, `meta()`, `url()`, `scheme()`, `host()`, `domain()`,
`under_maintenance()`, `launchdate()`, `age()`, `is_newcomer()` and
`execution_time()`; `app.email` (`EmailVariable`) the same site's `title()`,
`slogan()`, `logo()`, `homepage()`, `address()` for an e-mail; `app.random`
`uuidv4()` and `rand()`.

## Addresses, files and pages

| Name | Kind | What it gives |
|---|---|---|
| `url(name, parameters = [], referenceType = absolute)` | function, filter | `AdvancedRouter::getUrl()`: a route's address (always absolute inside an e-mail) |
| `asset(nameOrUrl, package = null)` | function, filter | `AdvancedRouter::getAssetUrl()` |
| `embed(src, options = [])` | function, filter | A file embedded in an e-mail (`MailTwigExtension::source()`) |
| `inline_css_email(body, ...css)` | filter | The body with the stylesheets inlined |
| `obfuscate` | filter | `Obfuscator::encode()`: the `data` of the media routes |
| `ondisk` | test | Whether a path exists under `public/` |
| `embed_url(url, remote = true)` | function | What a page can frame of an address ([Pages and texts](../40-commons/pages-and-texts.md)) |
| `qr_code(data, size = 600)` | function | A QR code as an SVG `data:` URI ([Downloads and QR codes](../40-commons/downloads-and-qr.md)) |
| `deploy_version()` | function | Eight hexadecimal characters that change when the application's git `HEAD`, or that of a package checked out under `vendor/glitchr`, `vendor/omnisense` or `vendor/omnibase`, changes; `dev` when none is a git checkout |
| `oauth_available(name)` | function | Whether `hwi_oauth` declares that resource owner on some firewall with a client id |
| `render_stylesheet(href, attributes = [], keepIfNotFound = true)`, `render_javascript(src, ...)` | functions | A `<link>` / `<script>` for a public file; with `keepIfNotFound` false, nothing when the file is missing or empty |
| `html_entry_link_tags()`, `html_entry_script_tags()`, `html_entry_head_tags()`, `html_entry_noscript_tags()`, `html_entry_body_tags()` | functions | The tags `HtmlTagRenderer` collected for the page |
| `encore_entry_link_tags()`, `encore_entry_script_tags()`, `encore_entry_css_source()` | functions | omnibase's webpack renderer (`WebpackTagRenderer`): the entry's tags with the breakpoints and `-async`/`-defer` variants (`base.twig.breakpoints`, `script_attributes`) |

## Pictures and media

| Name | Kind | What it gives |
|---|---|---|
| `image(path, config = [], filters = [])` | function | `MediaService::image()`: the image route's address (cached locally) |
| `imageset(path, ...srcset)` | function | A `srcset` |
| `video(path, config, filters)`, `audio(...)` | functions | The media route's address |
| `embed_base64(path)` | function, filter | The image as a base64 `data:` URI |
| `imagify(attributes = [], ...srcset)`, `vidify(attributes)`, `soundify(attributes)` | filters | An `<img>`, `<video>`, `<audio>` tag |
| `lightbox` | filter | The image in a lightbox's markup |
| `crop(x, y, width, height, position)` | filter | A cropped image's address |
| `thumbnail(width, height, config, filters)` and `thumbnail_inset`, `thumbnail_outbound`, `thumbnail_noclone`, `thumbnail_upscale` | filters | A thumbnail's address, in that mode |
| `picture(width = null, height = null)` | filter | A stored picture's address: an upload through the image resolver at that size, a URL or a public path as it is, anything else under `public/assets/` |
| `public(storage = null)`, `downloadable(config)` | filters | `FileService`: a stored file's public address, its download address |
| `mimetype`, `extensions`, `filesize(unitPrefix)` | filters | A file's MIME type, its extensions, a size in bytes made readable |
| `obfuscate_file(config)`, `obfuscate_image(config, filters)` | filters | The obfuscated `data` of a file, of an image |

## Texts and links

| Name | Kind | What it gives |
|---|---|---|
| `wysiwyg(options = [])` | filter | A stored text (Editor.js or HTML) rendered: mentions, links, media, anchored headings ([Pages and texts](../40-commons/pages-and-texts.md)); print it with `\|raw` |
| `wysiwyg_toc(maxLevel = null)` | filter | Its headings: a list of `{tag, slug, title}` |
| `semantify(attributes = [])`, `semantify_only(words, attributes = [])` | filters | The text with its semantic words (or the ones given) linked |
| `urlify(label = null, attributes = [])` | filter | A link to an address or a `LinkableInterface` record; class `highlight` when it is the page's own |
| `linkify` | filter | A `LinkableInterface` record's address |
| `iconify(attributes = [], wrap = true)` | filter | An icon's name, a list, an enumeration's or an `IconizeInterface`'s icons, as HTML |
| `highlight(pattern, gateLength = -1)` | filter | The words matching a pattern (or a list) wrapped as highlighted, accents ignored |
| `htmlify(options = [])` | filter | An `HtmlizeInterface` object's HTML (`__toHtml()`, else its template) |
| `crudify(options = [])` | filter | A link to an entity's page in the back office (`@Base/admin-legacy/crudify.html.twig`), empty without a CRUD for it |
| `mailto(address, label)` | function, filter | `<a href='mailto:...'>label</a>` |
| `mailparse(addresses)`, `mailparse_email(recipient)`, `mailparse_name(recipient)` | functions | An address list parsed (`address => name`), its first address, its first name |
| `title(name, parameters = [], domain = 'controllers', locale = null)`, `excerpt(...)` | functions | The text of `<name>.title` / `<name>.excerpt` in that domain, else `@<domain>.<name>.title` |

## Translation, locales and themes

| Name | Kind | What it gives |
|---|---|---|
| `trans(parameters, domain, locale, recursive = true)` | filter | `Base\Service\Translator::trans()`, in place of Symfony's filter: `@domain.key` references resolved, the politeness level applied ([How the site addresses people](../20-architecture/politeness.md)) |
| `trans_quiet`, `trans_exists` | filters | The text or `null` when the catalogue has none; whether it has one |
| `trans_time` | filter | A number of seconds in words |
| `trans_entity(property = null, options = singular)`, `trans_entityExists` | filters | An entity's (or class's) noun, a property's label |
| `trans_enum(class, options = singular)`, `trans_enumExists` | filters | An enumeration value's words |
| `parse_class(parseBy)` | filter | A class name as the translator turns it into a key |
| `enum(classOrTypeName)` | function | An enumeration by its class or its DBAL type's name (`thread_state`); a list for a list |
| `colorify(entry = null)` | filter | The colours of a `ColorizeInterface`, an enumeration's by value |
| `lang`, `lang_name`, `country`, `locale_country_name` | filters | A locale's language, its language's name, its country, its country's name (`fr-BE`: `fr`, `BE`, Belgium) |
| `country_name(locale = null)` | filter | A country code's name (`'JP'\|country_name('de')`) |
| `compatible_locale(...)` | function | `Localizer::compatibleLocale()` |
| `render_locale(options, template = '@Base/localizer/locale_dropdown.html.twig')`, `render_timezone(options, template)` | functions | The language and time-zone menus |
| `mode`, `filters` | filters | `Themizer::getMode()`, `getFilters()` |
| `compatible_theme(...)` | function | `Themizer::compatibleLocale()` |
| `render_theme_modes(options, template)`, `render_theme_filters(...)`, `render_theme_widths(...)` | functions | The theme menus (`base.twig.themes`) |
| `datetime(pattern = 'YYYY-MM-dd HH:mm:ss', dateFormat, timeFormat, timezone, calendar, locale)` | filter | A moment (a `DateTime`, a timestamp, an ISO 8601 string) formatted by an ICU pattern, lowered; a list of patterns gives a list |
| `countdown(parameters = [])`, `progress(end, parameters)` | filters | The countdown and the progress bar of `@Base/progress/` |

## Money

| Name | Kind | What it gives |
|---|---|---|
| `format_currency(currency, attrs = [], locale = null)` | filter | In place of twig/intl-extra's filter of that name: an amount in minor units (divided by `attrs.scale`, 100 by default) formatted, converted to the currency the visitor sees when a rate is known (`attrs.use_rate`, true by default). In the harness `{{ 1234\|format_currency('EUR') }}` prints `€ 12.34` |
| `apply_currency_rate(currency)` | function, filter | The amount converted to the visitor's currency, or `null` without a rate |

## The account, notifications and audience

| Name | Kind | What it gives |
|---|---|---|
| `account_profile(user)` | function | A member's profile as its view model (`Base\Screen\Account\ProfilePage`), as the visitor sees it |
| `notifications_unread()`, `notifications_latest(limit = 8)` | functions | The signed-in user's unread count, latest notifications (0 and `[]` for a visitor) |
| `notifications_token()` | function | The CSRF token of the intent `notifications` |
| `notification_admin_url(notification)` | function | The back-office page a notification is about, or `null` |
| `push_available()`, `push_vapid_key()` | functions | Whether web push is configured and a user is signed in; the VAPID public key |
| `analytics_page_views(path = null, window = null, source = null)`, `analytics_unique_visitors(window)`, `analytics_unique_users(window)`, `analytics_summary()`, `analytics_daily_breakdown(days = 14)`, `analytics_week_over_week_change()` | functions | `Base\Service\Analytics`'s counters (`window`: `today`, `7d`, `30d`...; `source: 'ai'` the AI crawlers' hits) |
| `demo_mode()`, `demo_accounts()` | functions | Whether the kernel runs `demo`; the declared accounts a visitor may sign in as ([The demo environment](../20-architecture/demo.md)) |
| `opening_hours(scope = null)` | function | `Base\Service\OpeningHours`, or one place's ([Opening hours](../40-commons/opening-hours.md)) |
| `local_business(overrides = [], scope = null)`, `local_business_jsonld(...)` | functions | schema.org's LocalBusiness, as an array or as its `<script type="application/ld+json">` ([LocalBusiness JSON-LD](../40-commons/local-business.md)) |

## Layout pieces

| Name | Kind | What it gives |
|---|---|---|
| `render_slot(slot, options = [], template = null)` | function | A widget slot's widget rendered (empty when the slot or its widget is missing) |
| `all_slots()`, `all_widgets()` | functions | `WidgetProvider`'s slots and widgets |
| `render_breadcrumb(name, request, options = [])` | function | A breadcrumb (`Breadgrinder`) rendered |
| `paginator(pagination, routeName, parameters = [])` (or `paginator_slidingcontrol`) | function | The pagination's template rendered; `paginator_first`, `_previous`, `_current`, `_next`, `_last`, `_rewind`, `_fastforward`, `_firstOnes`, `_previousOnes`, `_nextOnes`, `_lastOnes`, `_firstSeparator`, `_lastSeparator` give its parts |
| `share(identifier, options = [], template = null)` | filter on a `LinkableInterface` | A sharing button (an adapter tagged `base.service.sharing`) |
| `clipboard(template, value, attributes = [])`, `clipboard_target(template, '#target', attributes = [])` | functions | A copy button (`@Base/clipboard/<template>.html.twig`); the target must start with `#` |
| `form_proxy(name)`, `form_view(name)` | functions | A form registered with the `FormProxy`, and its view |

## Generic helpers

| Name | Kind | What it gives |
|---|---|---|
| `count(value, mode)`, `sqrt(value)` | functions | PHP's |
| `round(num, precision = 0, mode)` | function | Rounded (see the defect below) |
| `get_class`, `class_exists`, `get_short_class(object)` | functions | PHP's; the short class name, first letter lowered (`appVariable`) |
| `method_exists`, `is_callable`, `is_countable`, `is_linkable`, `is_json`, `is_string`, `empty` | functions | Tests as functions (`is_callable`, `is_countable`, `is_linkable`, `is_json`, `is_uuidv4` and `empty` are filters too) |
| `static_call(class, method, ...args)`, `static_property(class, name)` | functions | A static method's result, a static property's value |
| `call_user_func_with_defaults(fn, ...args)`, `call_user_func_if_exists(name, args)` | functions | A callable called; a Twig function called by its name, `''` when there is none |
| `property_accessor(object, path, enableMagicCall = false)` | function | A property path read |
| `cast(object, class, ...args)` | function | An object copied into another class |
| `count_leaves`, `synopsis`, `addslashes`, `exit` | functions | The leaves of an array; a class's synopsis dumped (debug); PHP's `addslashes`, `exit` |
| `email_preview()` | function | True when the template is rendered outside an e-mail (no `email` in the context) |
| `str_starts_with`, `str_ends_with` | functions | PHP's |
| `str_shorten(length = 100, position, separator = ' [..] ')`, `mb_ucwords`, `url_decode`, `basename`, `intval`, `strval`, `addslashes`, `ceil`, `floor`, `sign` | filters | Strings and numbers (`sign`: `+` or `-`) |
| `uniq`, `pad(length, value)`, `array_flatten`, `head`, `tail`, `third`, `fourth`, `fifth`, `closest(position)`, `pickup(n)`, `transforms(arrow)` | filters | Arrays: unique values, padded, flattened, first element, the rest, the 3rd/4th/5th, the element at a position (or the nearest end), `n` random entries, a closure applied |
| `distance(other)`, `greater_than(seconds)`, `instanceof(class)`, `nargs`, `stringify`, `preg_split(pattern)` | filters | The distance between two vectors; whether a date is older than that; `is_instanceof()`; a closure's number of parameters; any value as a string; `preg_split()` |
| `color_name` | filter | The nearest CSS named colour of a `#RRGGBB` code (`'#ff0001'` is `red`), the same in every locale (`Base\Service\Model\Color\Intl\Colors`: CSS's names, symfony/intl ships none) |

`round(number, precision = 0, mode)` keeps its decimals: `{{ round(3.14159, 2) }}` is `3.14`.

## In the harness

```twig
round(3.14159, 2) = {{ round(3.14159, 2) }}
-3|sign = {{ (-3)|sign }}
str_shorten = {{ 'Lorem ipsum dolor sit amet, consectetur adipiscing elit'|str_shorten(20) }}
'JP'|country_name('de') = {{ 'JP'|country_name('de') }}
'fr-BE'|lang = {{ 'fr-BE'|lang }} / country = {{ 'fr-BE'|country }}
mailparse_email = {{ mailparse_email('Ada <ada@example.org>') }} / name = {{ mailparse_name('Ada <ada@example.org>') }}
mailto = {{ mailto('ada@example.org', 'Ada') }}
[1,[2,3]]|count_leaves = {{ [1,[2,3]]|count_leaves }}
qr_code = {{ qr_code('https://example.org', 100)|slice(0, 26) }}...
get_short_class = {{ get_short_class(app) }}
is_json('{"a":1}') = {{ is_json('{"a":1}') ? 'true' : 'false' }}
'2026-04-10T18:05:00+00:00'|datetime('dd MMMM yyyy') = {{ '2026-04-10T18:05:00+00:00'|datetime('dd MMMM yyyy') }}
deploy_version() = {{ deploy_version() }}
demo_mode() = {{ demo_mode() ? 'true' : 'false' }}
```

rendered by the harness's `twig` service (`test` environment):

```
round(3.14159, 2) =
Deprecated: Implicit conversion from float 3.14 to int loses precision in /srv/demo/vendor/omnisense/omnibase/src/Twig/Extension/MathTwigExtension.php on line 35
3
-3|sign = -
str_shorten = Lorem ipsum dolor si [..]
'JP'|country_name('de') = Japan
'fr-BE'|lang = fr / country = BE
mailparse_email = ada@example.org / name = Ada
mailto = <a href='mailto:ada@example.org'>Ada</a>
[1,[2,3]]|count_leaves = 3
qr_code = data:image/svg+xml;base64,...
get_short_class = appVariable
is_json('{"a":1}') = true
'2026-04-10T18:05:00+00:00'|datetime('dd MMMM yyyy') = 10 april 2026
deploy_version() = 4e52a720
demo_mode() = false
```
