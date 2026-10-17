---
title: The router's scheme is the request's
order: 10
---

# The router's scheme is the request's

Until 2026-10-17 `AdvancedRouter::getScheme()` read

```php
$use_https ??= $_SERVER["REQUEST_SCHEME"] ?? true;
return $use_https ? "https" : "http";
```

so it answered `https` whatever the request: `REQUEST_SCHEME` is a string,
and `"http"` is as true as `"https"`. Only an empty `REQUEST_SCHEME` (or
`"0"`) gave `http`. It now answers the scheme of the request being handled.

## What it answers

- **A request on the stack**: `get_url_scheme()` of the main request -
  `Request::isSecure()` (`HTTPS` on, or `X-Forwarded-Proto: https` from an
  address within `framework.trusted_proxies`), then, without `HTTPS`, the
  variables the core always read (`USE_HTTPS`, `REQUEST_SCHEME`,
  `X-Forwarded-Proto`). It is the function `get_url()` gives the visited
  address and the site's routes their scheme with (f115e368), so the router
  and the addresses agree.
- **A web request not yet on the stack**: the same function on PHP's
  globals.
- **No request** (a command, a worker, a test between two requests):
  `https`, unless the site sets `HTTPS` (or `USE_HTTPS`) to a false value
  (`off`, `false`, `0`) in its environment. PHP's globals hold no scheme in
  a console, and `get_url_scheme()` would say http there: links written by
  a command keep the https they had.

## What it decides

It does **not** decide the scheme of the addresses of the site's own routes:
`AdvancedUrlGenerator::resolveParameters()` sets the request context's
scheme from `get_url()`, and `https` when no request gives one. The visited
address (`get_url()`) and its port (`getPort()`, 532f991a) follow the
request too.

`getScheme()` decides:

- the scheme of the absolute addresses of Symfony's own routes (`_profiler`,
  `_wdt`, `_switch_locale`...), and of every route when the router's
  advanced features are off (`AdvancedUrlGenerator::generate()` replaces
  `http://` and `https://` by it);
- which declared ports the host fallbacks are filtered against
  (`getFallbackParameters()`: `HTTPS_PORT` on https, `HTTP_PORT` on http),
  whether a fallback port is the scheme's default (`getPortFallback()`), and
  the scheme `getPort()` judges by when the address has none;
- the scheme of the address a request outside the allowed IPs is sent to
  (`RouterSubscriber`), the host `getUrl()` strips from an address asked as a
  path, and `app.site.scheme`.

## What moved

On every site served over https (all of them, behind a proxy that terminates
TLS, in development and in production) nothing changes: the request is
https, as the router said. Where a site is answered over plain http - the
harness (`http://localhost:8000`), the sites' tests (`http://localhost`),
latoucheoriginale's tests on 8081 - the router now says http:

- an address of `_profiler` or `_wdt` is http, as the page is;
- the fallbacks' ports of an http request are filtered by `HTTP_PORT`, as
  `getPort()` already judged the visited port: latoucheoriginale's
  (`HTTP_PORT='[8081]'`, no `HTTPS_PORT`) is 8081, where `HTTPS_PORT`'s
  `[443]` made it 443 and the IP restriction sent to `https://localhost/`;
- `AdvancedUrlGenerator::generate()` no longer forces an absolute address
  when `HTTPS` is on in the environment while the scheme is http. The rule
  never fired while the scheme was always https, and following the request
  it would have written every link of a site's tests (`.env.test` sets
  `HTTPS=on`, the client asks `http://localhost`) as an absolute address
  with the request's own scheme and host, which a path says as well. A
  route with a host of its own is still written whole.

## How it is checked

`tests/Routing/RouterSchemeTest`: a plain http request is http (the
router, `_switch_locale` and `security_login` absolute, a path still a
path); https is https; FrankenPHP behind nginx terminating TLS (`HTTPS` "",
`REQUEST_SCHEME` http, `X-Forwarded-Proto: https` on 8663) is https from a
trusted address and http from an untrusted one; without a request it is
https, http when the site sets `HTTPS=off` or `USE_HTTPS=false`; an http
site on 8081 keeps its port and scheme in its fallbacks, `getPort()`, its
links and through the kernel (200); the fallbacks' ports of a site with
`HTTP_PORT='[80,8094]'` and `HTTPS_PORT='[443,8473]'` are the https ones on
https and the http ones on http. Every test but the https one fails on the
previous code. `ForwardedHttpsUrlTest`, `NonDefaultPortUrlTest`,
`AbsoluteUrlWithoutRequestTest` and `BasicAuthUrlHttpTest` stay green, and
so do the core's, omnibase/admin's and omnibase/marketplace's suites in the
harness.

omnibase/marketplace's `QuickOrderTest` failure, once suspected of this, was
its own: a Location answered to an http request compared with
`QuickOrder::doneUrl()` made outside a request (marketplace d1d93bf).

Code that needs the scheme the visitor used may read the router's now, or
the request's (`get_url_scheme()`, `app.request.scheme`): they are the same.
