---
title: Front end
order: 48
---

# Glitch Art's signature

The same on every site: the year on two lines beside the pixel monkey (it
dances on hover), Source Code Pro bold 18pt/21px, linking to
https://www.glitchr.io. Its stylesheet (`bundles/base/css/credits.css`) is
linked by the partial.

```twig
{% include '@Base/partials/_credits.html.twig' %}                                   {# black #}
{% include '@Base/partials/_credits.html.twig' with {color: 'white', outline: true} %}
{% include '@Base/partials/_credits.html.twig' with {mirror: true, rule: true, site_name: 'Apfelschule'} %}
```

| Parameter | Values | |
|---|---|---|
| `color` | `black` (default), `white` | the text and the monkey; grey on hover. Never a colour of the site. |
| `outline` | `false`, `true` | a white contour, on a photo or a busy background |
| `mirror` | `false`, `true` | the detail line on the right of the signature instead of the left: for a signature set on the left of its footer |
| `rule` | `false`, `true` | a dotted rule between the detail's two lines |
| `detail` | `true`, `false` | the detail line at all |
| `site_name` | text | its second line |

The detail line (the address, the time the page took, the site's name) fades
in while the signature is pointed at or focused, and is always shown without a
pointer (`@media (hover: none)`). Filling it differently:

```twig
{% embed '@Base/partials/_credits.html.twig' with {color: 'black'} %}
    {% block credits_detail %}{{ parent() }}<span class="ga-credits-line" lang="de">Ein Apfel am Tag</span>{% endblock %}
{% endembed %}
```

The signature stands against the edge the site sets it on, whether its detail
is shown or not: where the detail fades in and out (`@media (hover: hover)`) it
holds no room and overflows on its own side. It is declared on the inner side:
the default (detail on the left) for a signature on the right of its footer,
`mirror: true` (detail on the right) for one on the left - else the detail
opens outwards, over the page's margin. Without a pointer the detail is always
shown and part of the block.

Nothing else is customisable: neither the font nor the colours.

# `Base.boot()`

What every site's `assets/app-defer.js` repeated: `Sticky.ready()`,
`StickyStops.attach()` and `Transparent.ready()` in that order, the back office
as a website-in-website. The three come from `@glitchr/flowjs` (one library, one
module per feature; the stops measure themselves again after each navigation).
Install it from npm (`yarn add @glitchr/flowjs`) and import only the modules the
site uses: `Base.boot()` starts a module only if its global is there, so a site
that imports the navigation alone starts the navigation alone. The core's own
bundle imports none of them - in particular not `Ajaxer`, which a site that
sends named AJAX channels imports itself (`import '@glitchr/flowjs/ajaxer'`).

The one module the core does import is the library's sixth, the cookies:
`base-async` brings `@glitchr/flowjs/cookie` (the `Cookie` global, with
flowjs's core) to every page, see [Cookies](#cookies) below. The core's
`base-async` runs before a site's entries and the library keeps the first
`Flow` loaded, so **a site takes the same `@glitchr/flowjs` as the core**
(`^1.2.0` in its `package.json`, 1.2.0 or later in its `yarn.lock`): an older
navigation or sticky module would otherwise run on the newer core.

```js
import '@glitchr/flowjs/navigation';   // the modules the site uses: '/sticky', '/stops', '/navigation'
import Base from '../vendor/glitchr/omnibase/assets/boot.js';

Base.boot({
    nest: ['/admin*'],                                  // default
    stops: { reach: 140, settle: 320, duration: 700 },  // StickyStops' options; true: its defaults; false: none
    viewTransitions: false,
    exceptions: ['/agenda.ics'],                        // after /_*, /login*, /logout*, /register*, /locale/*, /connect/*
    current: '.site-nav a',                             // aria-current="page" follows the page
    transparent: { exit_duration: 380 },                // anything else for Transparent.ready(); false: not started
    sticky: false,                                      // Sticky.ready()'s options; false: not started
});
```

It also makes jQuery's `closestScrollable` safe, keeps the `<style>` blocks
scripts inject into `<head>` across swaps (`data-headlock`), and sends a site
page that lands inside the nest frame to the main window. The routes that
open in the nest answer `X-Transparent-Nest` (omnibase/admin's
`NestHeaderSubscriber`: every page under `/admin`, and any route with
`defaults: ['_nest' => true]`).

# `media:play`: one thing sounds at a time

A page may hold several players - the audio bar of omnibase/music, the video
player of omnibase/video, a film in its card, a third-party embed, a plain
`<audio>`. They share nothing but this contract:

1. a player that **starts sounding** dispatches `media:play` on `document`,
   with `detail: { player, kind?, id?, … }` - `player` is any object that is
   this player and no other;
2. a player that **hears** `media:play` from another player pauses itself.

No queue, no common state, no interface to implement: each player keeps its
own. `media.js` (no dependency) is those two lines written once:

```twig
<script src="{{ asset('bundles/base/js/media.js') }}" defer></script>
```

```js
import '../vendor/glitchr/omnibase/assets/media/media.js';     // bundled: the same script, window.MediaPlay

const leave = MediaPlay.join(player, () => player.pause());      // 2. paused when another starts
MediaPlay.announce(player, { kind: 'audio', id: track.id });     // 1. when this one starts
leave();                                                         // the player is destroyed
MediaPlay.current;                                               // the player that sounded last, or null
```

A script that does not load `media.js` takes part with the event alone:

```js
document.dispatchEvent(new CustomEvent('media:play', { detail: { player: me } }));
document.addEventListener('media:play', (event) => { if (event.detail.player !== me) pause(); });
```

Plain elements need nothing. An `<audio>` or a `<video>` that starts with its
sound on announces itself (`detail.player` is the element, `kind` its tag),
and is paused when another player starts. A **muted** one - a hero's silent
loop, a preview on hover - neither announces nor is paused; unmuted while
playing, it announces itself then. `data-media-play="off"` on an element, or
on anything around it, leaves it out (a sound effect; an element driven by a
player that calls `announce()` itself).

A third-party player that cannot be paused (an embed without an API) is
removed, or its frame reloaded, by the function given to `join()`; a player
whose pause fails does not keep the others from pausing.

# Stimulus `poll`

Ask a JSON address every few seconds and react when a value grows - a
kitchen board, a queue.

```js
// assets/bootstrap.js
import Poll from '../vendor/glitchr/omnibase/assets/controllers/poll_controller.js';
app.register('poll', Poll);
```

```twig
<div data-controller="poll" data-poll-url-value="{{ path('app_kitchen_state') }}"
     data-poll-key-value="latest" data-poll-since-value="{{ latest }}"
     data-poll-chime-value="true" data-poll-awake-value="true"
     data-action="poll:change->board#show">
    <button data-action="poll#arm">Activer les alertes</button>
    <button data-action="poll#silence">Vu</button>
</div>
```

The answer must hold the compared field (`{"latest": 42, ...}`). On a larger
value: `poll:change` (detail: the answer), the class `is-ringing`, and with
`chime` three notes every two seconds until `poll#silence`. `arm()` is the
touch browsers ask before a page may make sound; it also keeps the screen on
(`awake`). A hidden page is not polled.

# Nothing from elsewhere

A page of a site loads its scripts, stylesheets and fonts from the site
itself: no CDN sees the visitor (`tests/Http/NothingFromElsewhereHttpTest`
reads the contact page of the harness - which links `base` and `form` as a
site's layout does - and what its files pull in).

The editor's code blocks (editorjs-code-highlight) are coloured by
highlight.js's default theme. The package's own bundle `@import`s it from
cdnjs.cloudflare.com, and injects it with its styles on every page the
editor's script is on - every page with a form. That `@import` is dropped
at build (`assets/loaders/no-remote-import.js`); the theme is the bundle's
copy, `bundles/base/css/highlight.js/default.css` (highlight.js 11.6.0,
BSD 3-Clause, its licence beside it as `LICENSE.txt`), linked by the
editor's script once, and only on a page that shows an editor or a block of
code.

The emoji picker (`EmojiPickerType`, entry `form.emoji`, picmo) fetched its
emojis and their labels from jsDelivr. They are the bundle's copy:
`bundles/base/emoji/<locale>/data.json` and `messages.json` (emojibase-data
17.0.0, MIT, its licence beside them as `LICENSE.txt`), in English, French,
German and Japanese - the page's `<html lang>` picks, English otherwise.
`form-type-emoji.js` fetches them on the first click on a field and gives
them to the picker, which then fetches nothing; what remains of picmo's own
fetching is pointed at the same copy at build
(`assets/loaders/local-emoji-data.js`). The harness's `/fields` carries one.

# The JavaScript dependencies

`yarn.lock` names every package the bundles are built from, and `make assets`
installs those alone (`yarn install --frozen-lockfile`): from a clean copy it
rebuilds `bundles/base` byte-identical to what is committed. To take newer
packages: `ALLOW_ASSETS_UPDATE=1 make assets`, then commit the lock with the
bundles it built. Node 24 or later (select2's engines).

# Cookies

`base-async` imports `@glitchr/flowjs/cookie`: the `Cookie` global on every
page (`Cookie.set(group, name, value, expires)`, `Cookie.get()`,
`Cookie.cookies`, `Cookie.local`, `Cookie.session`, `Cookie.use()`, see the
library's `docs/cookie.md`). It replaces `@glitchr/cookie`, whose methods and
`localStorage` keys (`cookie/<GROUP>`) it keeps: a site drops
`@glitchr/cookie` from its `package.json` and, if it imported it itself,
imports nothing in its place.

What is written goes through it, so that the visitor's answer decides
(omnibase/consent declares the categories and shows the panel; without that
bundle a group is written until `Cookie.setConsent(false, group)` refuses it):

| Cookie | Written by | Under |
| --- | --- | --- |
| `USER/INFO` | `assets/styles/js/user.js`: the browser's language, calendar, numbering system, light or dark theme | `USER` |
| `LOCATION/TIMEZONE` | `user.js`: the browser's time zone | `LOCATION`, when the site declares that category for its visitors to answer (omnibase/consent's default); else the zone stays in `USER/INFO` (`timezone`), under `USER` |
| `USER/THEME/*` | a site's theme switch | `USER` |
| `ANALYTICS/VISITOR_ID` | a site's own script, if it counts unique visitors (`Cookie.set('ANALYTICS', 'VISITOR_ID', ...)`) - the core only reads it (`PageViewSubscriber`) | `ANALYTICS` |

| `USER/LAST_VISIT` | the server, when a template asks `app.site.is_newcomer()` - not for a visitor who refused `USER` (`Base\Security\CookieAnswer::refused('USER')` reads the answer the script keeps in the cookie `cookie-consent`) | `USER` |

The server reads the others and writes none of them: `User::getCookie('timezone')`
is `LOCATION/TIMEZONE`, else `USER/INFO`'s; `getCookie('country')` is read
from that zone. A script of a site writes its own the same way
(`Cookie.set('USER', 'THEME/MODE', mode, ...)`, or
`Consent.cookie(group, name, value, expires)`), never with `document.cookie`:
what is written behind `Cookie` is neither kept from being written after a
refusal nor known to the detailed section.
