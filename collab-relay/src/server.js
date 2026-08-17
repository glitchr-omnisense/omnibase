#!/usr/bin/env node
'use strict';

// Auth-gated y-websocket relay for glitchr/base-bundle's real-time
// collaboration feature.
//
// Persistence: a room's Y.Doc lives in memory only — this relay has no
// database of its own. Content rooms (editorjs-yjs, holding a non-empty
// `blocks` Y.Array) get their content flushed into the app's own database
// via a debounced POST to ux_editorjs_autosave, authenticated as a service
// call (see mintServiceToken below) rather than a user session, since
// there's no browser/CSRF context on this side. Presence-only rooms
// (regular fields — form-type-collab-presence.js) never write to
// `blocks`, so their Y.Doc never fires an `update` event and this bridge
// naturally never triggers for them — no special-casing needed, see
// wss.on('connection', ...) below.
//
// Deliberately pinned to y-websocket@2.x (see package.json): the current
// "y-websocket" package (3.x) is client-only and targets yjs v13, while the
// new server package ("@y/websocket-server") targets yjs v14, which is
// still a pre-release and NOT wire-compatible with a yjs-v13 client. 2.x is
// the last version that ships a matched client+server pair on the stable
// yjs v13 line — verified by installing both and inspecting node_modules
// before writing this file, not assumed from documentation.
//
// Auth: the browser first calls ux_editorjs_collabTicket (Symfony,
// session-authenticated + CSRF-protected) to obtain a short-lived ticket —
// base64url(payload) + "." + hmac_sha256(payload, COLLAB_TICKET_SECRET),
// where payload is {uid, name, avatar, color, room, exp}. This relay
// verifies the signature and expiry itself; it never talks to PHP or the
// session store, so COLLAB_TICKET_SECRET must be shared out-of-band with
// the app (a dedicated secret, not Symfony's APP_SECRET — keeps the blast
// radius of a compromised relay separate from session/CSRF signing).

const http = require('http');
const crypto = require('crypto');
const WebSocket = require('ws');
const { setupWSConnection, getYDoc } = require('y-websocket/bin/utils');

const PORT = parseInt(process.env.COLLAB_RELAY_PORT || '1234', 10);
const SECRET = process.env.COLLAB_TICKET_SECRET;
const AUTOSAVE_URL = process.env.COLLAB_AUTOSAVE_URL || '';
const PERSIST_DEBOUNCE_MS = parseInt(process.env.COLLAB_PERSIST_DEBOUNCE_MS || '5000', 10);
const TICKET_MAX_AGE_SKEW_S = 5; // small clock-skew allowance, not a TTL extension

if (!SECRET) {
    console.error('COLLAB_TICKET_SECRET is not set — refusing to start (every ticket would fail verification).');
    process.exit(1);
}
if (!AUTOSAVE_URL) {
    console.warn('COLLAB_AUTOSAVE_URL is not set — live collaboration will work, but content will never be persisted to the app database.');
}

function base64url(buf) {
    return buf.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function base64urlDecode(str) {
    return Buffer.from(str.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8');
}

/**
 * Verifies a ticket against the room the client is trying to join. Returns
 * the decoded payload on success, or null on any failure (bad signature,
 * expired, malformed, or minted for a different room than requested).
 */
function verifyTicket(ticket, room) {
    if (!ticket || typeof ticket !== 'string') return null;

    const dot = ticket.lastIndexOf('.');
    if (dot < 0) return null;

    const payloadPart = ticket.slice(0, dot);
    const signaturePart = ticket.slice(dot + 1);

    const expectedSignature = crypto.createHmac('sha256', SECRET).update(payloadPart).digest('hex');

    const expectedBuf = Buffer.from(expectedSignature, 'hex');
    const providedBuf = Buffer.from(signaturePart, 'hex');
    if (expectedBuf.length !== providedBuf.length) return null;
    if (!crypto.timingSafeEqual(expectedBuf, providedBuf)) return null;

    let payload;
    try {
        payload = JSON.parse(base64urlDecode(payloadPart));
    } catch (e) {
        return null;
    }

    if (!payload || typeof payload !== 'object') return null;
    if (typeof payload.exp !== 'number' || (Date.now() / 1000) > (payload.exp + TICKET_MAX_AGE_SKEW_S)) return null;
    if (payload.room !== room) return null; // ticket minted for a different room — reject reuse

    return payload;
}

/**
 * Expects /collab/<urlencoded room key>. The room key itself already
 * contains ":" separators (fqcn:id:field:locale) so it's carried as a
 * single encoded path segment rather than split across several.
 */
function roomFromPath(pathname) {
    const match = pathname.match(/^\/collab\/([^/]+)$/);
    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * room is "fqcn:id:field:locale" — fqcn is a PHP namespace (backslashes,
 * never colons), so a plain split is safe. Returns null for anything not
 * shaped like a CollabRoomResolver::buildRoom() room key.
 */
function parseRoom(room) {
    const parts = room.split(':');
    if (parts.length !== 4) return null;
    const [fqcn, id, field, locale] = parts;
    return { fqcn, id, field, locale: locale === '_' ? null : locale };
}

/**
 * Same wire format as verifyTicket()'s tickets, minus the user fields —
 * this relay signs its OWN short-lived token to authenticate its autosave
 * POST back to ux_editorjs_autosave, verified there via
 * CollabTicketFactory::verifyServiceToken() against the same shared
 * secret used for the browser-facing tickets.
 */
function mintServiceToken(room) {
    const payload = { room, exp: Math.floor(Date.now() / 1000) + 30 };
    const payloadB64 = base64url(Buffer.from(JSON.stringify(payload)));
    const signature = crypto.createHmac('sha256', SECRET).update(payloadB64).digest('hex');
    return payloadB64 + '.' + signature;
}

const persistTimers = new Map(); // room -> Timeout
const lastKnownVersion = new Map(); // room -> version string returned by the last successful/rejected save
const instrumentedRooms = new Set(); // rooms whose Y.Doc already has an update listener attached

async function persistRoom(room, doc) {
    persistTimers.delete(room);

    if (!AUTOSAVE_URL) return;

    const parsed = parseRoom(room);
    if (!parsed) return;

    const blocks = doc.getArray('blocks').toArray();
    if (blocks.length === 0) return; // nothing written yet, or not a content room at all

    const value = JSON.stringify({ time: Date.now(), blocks });

    try {
        const res = await fetch(AUTOSAVE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                serviceToken: mintServiceToken(room),
                room,
                fqcn: parsed.fqcn,
                id: parsed.id,
                field: parsed.field,
                locale: parsed.locale,
                value,
                baseVersion: lastKnownVersion.get(room) || null,
            }),
        });
        const json = await res.json().catch(() => null);

        // A 409 still carries the server's current version — adopt it as
        // our new baseline either way, so a stale relay-side baseVersion
        // (e.g. someone saved through the plain browser autosave path
        // concurrently) doesn't keep rejecting every subsequent attempt.
        // The room's live Yjs state remains authoritative for connected
        // clients regardless of whether any given persistence POST landed.
        if (json && json.version) lastKnownVersion.set(room, json.version);

        if (!res.ok && res.status !== 409) {
            console.error(`collab relay: persistence POST for room ${room} failed with status ${res.status}`);
        }
    } catch (e) {
        console.error(`collab relay: persistence POST for room ${room} threw`, e);
    }
}

function schedulePersist(room, doc) {
    if (persistTimers.has(room)) clearTimeout(persistTimers.get(room));
    persistTimers.set(room, setTimeout(() => persistRoom(room, doc), PERSIST_DEBOUNCE_MS));
}

const httpServer = http.createServer((req, res) => {
    if (req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'text/plain' });
        res.end('ok');
        return;
    }
    res.writeHead(404);
    res.end();
});

const wss = new WebSocket.Server({ noServer: true });

httpServer.on('upgrade', (req, socket, head) => {
    let url;
    try {
        url = new URL(req.url, 'http://internal');
    } catch (e) {
        socket.destroy();
        return;
    }

    const room = roomFromPath(url.pathname);
    const ticket = url.searchParams.get('ticket');
    const payload = room ? verifyTicket(ticket, room) : null;

    if (!payload) {
        socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
        socket.destroy();
        return;
    }

    wss.handleUpgrade(req, socket, head, (ws) => {
        ws.collabUser = {
            id: payload.uid,
            name: payload.name,
            avatar: payload.avatar,
            color: payload.color,
        };
        wss.emit('connection', ws, req, room);
    });
});

wss.on('connection', (ws, req, room) => {
    // getYDoc is idempotent (map.setIfUndefined) — this returns the exact
    // same WSSharedDoc setupWSConnection() itself will look up below, so
    // attaching the persistence listener here, once per room via
    // instrumentedRooms, works regardless of connection order. gc:true —
    // Yjs's own tombstone cleanup, unrelated to (and compatible with) this
    // external persistence bridge; keeps memory bounded for long-lived
    // rooms with lots of churn.
    const doc = getYDoc(room, true);
    if (!instrumentedRooms.has(room)) {
        instrumentedRooms.add(room);
        doc.on('update', () => schedulePersist(room, doc));
    }

    setupWSConnection(ws, req, { docName: room, gc: true });

    // Flush immediately once the room goes idle, rather than waiting out
    // the full debounce on a doc nobody's looking at anymore.
    ws.on('close', () => {
        if (doc.conns.size > 0) return;
        if (persistTimers.has(room)) { clearTimeout(persistTimers.get(room)); persistTimers.delete(room); }
        persistRoom(room, doc);
    });
});

httpServer.listen(PORT, () => {
    console.log(`collab relay listening on :${PORT}`);
});

module.exports = { verifyTicket, roomFromPath, parseRoom, mintServiceToken };
