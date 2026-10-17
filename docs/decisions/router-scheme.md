---
title: The router's scheme stays https
order: 10
---

# The router's scheme stays https

`AdvancedRouter::getScheme()` reads

```php
$use_https ??= $_SERVER["REQUEST_SCHEME"] ?? true;
return $use_https ? "https" : "http";
```

so it answers `https` whatever the request: `REQUEST_SCHEME` is a string,
and `"http"` is as true as `"https"`. Only an empty `REQUEST_SCHEME` (or
`"0"`) gives `http`. It is a slip, and it is left as it is (2026-10-17).

## What it decides, and what it does not

It does **not** decide the scheme of the addresses of the site's own routes.
`AdvancedUrlGenerator::resolveParameters()` sets the request context's
scheme from `get_url()`: the request's (`get_url_scheme()`:
`Request::isSecure()`, a trusted proxy's `X-Forwarded-Proto`, f115e368), and
`https` when no request gives one (a command, a worker, a test between two
requests: `get_url()` is then `/`). The visited address (`get_url()`) and its
port (`getPort()`, 532f991a) follow the request too.

`getScheme()` decides:

- the scheme of the absolute addresses of Symfony's own routes (`_profiler`,
  `_wdt`...), and of every route when the router's advanced features are off
  (`AdvancedUrlGenerator::generate()` replaces `http://` and `https://` by it);
- which declared ports the host fallbacks are filtered against
  (`getFallbackParameters()`: `HTTPS_PORT` on https, `HTTP_PORT` on http),
  whether a fallback port is the scheme's default (`getPortFallback()`), and
  the scheme `getPort()` judges by when the address has none;
- in `AdvancedUrlGenerator::generate()`, whether a path is forced to an
  absolute address because `HTTPS` is on in `$_ENV` while the scheme is http;
- the scheme of the address a request outside the allowed IPs is sent to
  (`RouterSubscriber`), the host `getUrl()` strips from an address asked as a
  path, and `app.site.scheme` (no template of the core, the bundles or the
  sites reads it).

On every site - all https, behind a proxy that terminates TLS, in
development and in production - each of these answers is already right. It
is wrong only where a site is answered over plain http: the harness
(`http://localhost:8000`) and tests whose requests are `http://localhost`.
There, an address of `_profiler` says https, and the fallbacks' ports are
filtered by `HTTPS_PORT` while `getPort()` judges the visited port by
`HTTP_PORT`.

## Why it is not changed now

- **Following the request is not enough on its own.** In a console
  `get_url_scheme()` reads PHP's globals, which hold neither `HTTPS` nor
  `REQUEST_SCHEME`, and says `http`: the router would turn http for every
  command and worker unless it keeps https without a request.
- **It moves the ports.** The fallbacks' ports of an http request would be
  filtered by `HTTP_PORT` instead of `HTTPS_PORT`. That is the consistent
  answer (`getPort()` already judges by the address's scheme), but it is the
  logic 532f991a just corrected for lesringostars (https on 8663 behind
  nginx) and latoucheoriginale (http on 8081 in its tests); a site whose
  `HTTP_PORT` and fallbacks disagree would see its addresses change port.
- **What it would fix serves nobody yet.** No deployment is answered over
  http; the harness and the tests read right pages either way (relative
  paths, the request's own scheme for the site's routes).
- **The marketplace failure it was suspected of is not its doing.**
  omnibase/marketplace's `QuickOrderTest` compared a Location answered to an
  http request (http) with `QuickOrder::doneUrl()` called outside a request
  (https, the fallback above): `getScheme()` said https in both. The test
  was the one to correct (marketplace d1d93bf).

## What a fix needs

`getScheme()` returning `get_url_scheme($request)` for the main request and
`https` without one, with tests on http (a plain request), https (`HTTPS`
on), a proxy that terminates TLS (`HTTPS` `""`, `REQUEST_SCHEME` http,
`X-Forwarded-Proto: https` from a trusted address: https; the same from an
untrusted one: http) and the console (https); the fallback ports of an http
request filtered by `HTTP_PORT`; the core suite green, the bundles' and the
sites' suites run on it (lesringostars and latoucheoriginale first), and
each production proxy checked for `X-Forwarded-Proto` and an address within
`framework.trusted_proxies`. Until then, code that needs the scheme the
visitor used reads the request's (`get_url_scheme()`, `app.request.scheme`),
not the router's.
