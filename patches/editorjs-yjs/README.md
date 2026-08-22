# editorjs-yjs local fixes (v0.1.1)

`src/index.js` and `src/ContentBinding.js` here are the **fixed** copies of the
files of the same name in `node_modules/editorjs-yjs/`. They are kept verbatim
rather than as a `.patch` because there is no reliable pristine 0.1.1 baseline on
this machine to diff against (the yarn cache holds only 0.1.0), and a diff that
does not apply cleanly is worse than no diff at all.

## Why this exists

`yarn install` restores `node_modules/editorjs-yjs/` from the registry copy,
silently discarding these fixes. The built output under `public/` is committed
and so keeps working on its own — the danger is an install **followed by** an
asset rebuild, which regenerates the bundle from the reverted source and drops
the fixes with no error.

## Restoring after an install

    cp patches/editorjs-yjs/src/*.js node_modules/editorjs-yjs/src/
    # then rebuild the package's dist and this bundle's assets:
    #   (editorjs-yjs ships no config of its own here; see the note below)
    yarn prod

`editorjs-yjs`'s own `dist/bundle.js` is what `package.json`'s `main` points at,
so `src/` alone is not enough — the package must be rebuilt (webpack, UMD, with
`yjs` and `y-websocket` kept **external** so a second copy of yjs never lands on
the page) before this bundle's `yarn prod`.

## What the fixes are

1. **Block duplication.** `EditorYjs.attach()` handed over to
   `ContentBinding.attach()` without waiting for the provider's initial sync.
   `ContentBinding` decides "does this room already have content?" from
   `yarray.length`, which is necessarily still 0 while that sync is in flight, so
   every client took the "empty room" branch and pushed its own full copy.
   `Y.Array` entries are keyed by `(client, clock)` rather than by block id, so
   those pushes appended new entries instead of matching existing ones — the room
   grew by one whole copy per page load (observed: 235 → 267 → 275) and the
   relay's autosave bridge persisted the growth into the database. Fixed by
   awaiting `_connectReady` plus a new `_whenSynced()` (timed out, so an
   unreachable relay degrades to offline editing).

2. **Reconciliation hardening.** `applyRemoteToEditor()` read positions from a
   stale `editor.save()` snapshot while mutating the editor; positions are now
   re-read live per iteration via `_editorBlockIds()`, with indices clamped.
   `syncLocalToYArray()` deduplicates by id before touching the CRDT, inserts go
   through a clamping `_insertAt()`, and `_findYIndexById()` tolerates empty
   entries. These addressed EditorJS's "indices cannot be lower than 0 or greater
   than the amount of blocks" warning and Yjs's "Length exceeded!".

3. **Ticket expiry.** The ticket was refreshed only on a 45s `setInterval`
   against a short server-side TTL. Browsers clamp timers in background tabs to
   once a minute and suspend them during sleep, so the refresh slipped past the
   TTL and the following reconnect presented an expired ticket, which the relay
   rejects with 401 — surfacing as "WebSocket connection failed: There was a bad
   response from the server". The client now also refreshes on `connection-close`
   and on `visibilitychange`, and both listeners are removed in `destroy()`.
   `CollabTicketFactory`'s default TTL was widened from 60s to 300s as defence in
   depth.

## Upstream

These belong upstream, but the installed package's `repository.url`
(`public-repository/javascript/editor-js/yjs`) points at a **different** package —
that repo contains `y-editorjs@1.0.4`, with a different file set and no shared
history. The correct remote for `editorjs-yjs@0.1.x` is unknown here, so nothing
was pushed. A full fixed source tree also exists at `/var/www/pool/editorjs-yjs`
(its own git repo, no remote), with each fix as a separate commit.
