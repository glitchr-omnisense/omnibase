# base-bundle collab relay

Auth-gated [y-websocket](https://github.com/yjs/y-websocket) relay for
glitchr/base-bundle's real-time collaboration feature (`EditorType`'s
`collab_live` option, and regular fields via `FormTypeCollabExtension`'s
`collab: "live"`). Content rooms (a non-empty `blocks` Y.Array — i.e.
EditorJS fields, via `editorjs-yjs`) get their content persisted into the
app's database on a debounce, via a service-authenticated POST back into
`ux_editorjs_autosave`. Presence-only rooms (regular fields) never write to
`blocks`, so this never fires for them — see `persistRoom`/`schedulePersist`
in `src/server.js`.

Lives inside the bundle (rather than its own gitlab.glitchr.dev repo) so it
stays version-locked with whichever bundle version an app has installed, and
so a consuming app can build it straight out of its `vendor/` checkout with
no extra registry/repo to manage.

## Why a relay lives here at all

Symfony Messenger in this project is Doctrine-transport (DB-polling) only —
fine for background jobs, unsuitable for sub-second cursor/presence
broadcast. There's no Mercure/pub-sub infrastructure in this stack either.
This relay is deliberately small and stateless: an app that never enables
`collab_live` on any field never needs to deploy it at all.

## Auth model

The browser calls `ux_editorjs_collabTicket` (Symfony, session +
CSRF-protected) to get a short-lived ticket:

```
base64url(json_payload) + "." + hex(hmac_sha256(json_payload, COLLAB_TICKET_SECRET))
```

`json_payload` is `{uid, name, avatar, color, room, exp}`. This relay
verifies the signature and expiry itself — it never talks to PHP or the
session store — so `COLLAB_TICKET_SECRET` must be the same value on both
sides. Use a **dedicated** secret, not Symfony's `APP_SECRET`: keeps a
compromised relay from having any bearing on session/CSRF signing.

A client connects to `wss://<host>/collab/<urlencoded room>?ticket=<ticket>`.
The relay rejects the upgrade with `401` if the ticket is missing, expired,
tampered with, or was minted for a different room than the one being joined.

## Environment variables

| Variable                     | Required | Description                                                                                          |
|--------------------------------|----------|--------------------------------------------------------------------------------------------------------|
| `COLLAB_TICKET_SECRET`       | yes      | HMAC secret shared with the app's `COLLAB_TICKET_SECRET` env var. Also signs this relay's own service calls into `ux_editorjs_autosave` (see below) — one secret, symmetric use on both sides. |
| `COLLAB_RELAY_PORT`          | no       | Port to listen on (default `1234`).                                                                  |
| `COLLAB_AUTOSAVE_URL`        | no       | Full URL to the app's `ux_editorjs_autosave` action (e.g. `http://web/ux/editorjs/autosave`, reachable over the app's internal Docker network — no need to go through the public proxy). Without it, live collaboration still works, but content is never persisted past the in-memory Y.Doc. |
| `COLLAB_PERSIST_DEBOUNCE_MS` | no       | How long a content room must be quiet before its content is flushed to the database (default `5000`). Also flushed immediately once a room's last client disconnects. |

The app side also needs `COLLAB_RELAY_WS_URL` set (the public `wss://` URL
clients should connect to) — `CollabTicketFactory::isConfigured()` keeps
`collab_live` silently inert (no ticket minted) until both
`COLLAB_TICKET_SECRET` and `COLLAB_RELAY_WS_URL` are set on the app side.

## Deploying alongside the app

Add a service to the app's `docker-compose.yml` (same shape as the existing
`search`/`minio` third-party services), on `extranet` since browsers connect
to it directly (unlike `minio`, which stays `intranet`-only behind the
proxy) — but it also needs `intranet` to reach the `web` service for the
persistence bridge:

```yaml
  collab:
    container_name: ${APP_NAME}-collab
    build:
      context: ./vendor/glitchr/base-bundle/collab-relay
    restart: unless-stopped
    environment:
      COLLAB_TICKET_SECRET: ${COLLAB_TICKET_SECRET}
      COLLAB_AUTOSAVE_URL: http://web/ux/editorjs/autosave
    networks:
      - extranet
      - intranet
```

Then add a WebSocket-upgrade location to
`deployments/docker/proxy/conf.d/default.conf`, inside the existing `server
{ listen 443 ssl ... }` block (it already sets `Upgrade`/`Connection`
headers server-wide, so this location only needs `proxy_pass`):

```nginx
    location /collab/ {
        proxy_cache off;
        set $collab http://collab:1234;
        proxy_pass $collab;
    }
```

Set `COLLAB_RELAY_WS_URL=wss://<your-host>/collab` and the same
`COLLAB_TICKET_SECRET` in the app's `.env.local`.

## Local development

```sh
npm install
COLLAB_TICKET_SECRET=dev-secret npm start
```

`GET /health` returns `200 ok` once the relay is up — useful for the
Dockerfile's `HEALTHCHECK` and for a quick manual check.

## Local demo (relay + browser page, no Symfony app needed)

`docker-compose.yml` in this directory (distinct from the production
snippet above) runs the relay plus a static demo page in one command:

```sh
docker compose up --build
```

Then open **http://localhost:8088** — two independent `Y.Doc` +
`WebsocketProvider` connections to the same room, side by side in one page
(stands in for two browser tabs/users, "Marco" and "Sasha"). Typing a
message in one pane appears live in the other, and each pane's presence
list shows the other user's colored badge — this exercises the exact two
channels (content sync + awareness) that `editorjs-yjs` will use, using raw
`yjs`/`y-websocket` client APIs directly rather than that package (which
doesn't exist yet), so it's a way to confirm/debug the relay in isolation.

`example/client.js` mints its own ticket in-browser (Web Crypto HMAC)
against the same `dev-secret` the compose file starts the relay with — a
stand-in for `ux_editorjs_collabTicket`, which is the only real ticket
source once the Symfony app is involved. Rebuild the bundle after editing
`example/client.js` with `npm run example:build` (uses this project's own
pinned `yjs`/`y-websocket` versions via esbuild, so the demo always matches
what the relay actually runs).

Tear down with `docker compose down`.
