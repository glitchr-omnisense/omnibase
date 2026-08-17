// Local relay smoke-test page — deliberately NOT using editorjs-yjs (that
// package doesn't exist yet, see the bundle's collaboration plan, Milestone
// 3). This exercises the exact same two channels editorjs-yjs will use —
// a Y.Doc content array over the relay's sync protocol, and
// provider.awareness for presence — with raw yjs/y-websocket APIs, so it
// proves the relay itself works independently of any higher-level binding.
import * as Y from 'yjs';
import { WebsocketProvider } from 'y-websocket';

const WS_URL = window.COLLAB_DEMO_WS_URL || 'ws://localhost:1234';
const ROOM = 'demo\\Room:1:content:_';

// Ticket minting normally happens server-side (Base\Service\Collab\
// CollabTicketFactory, called via ux_editorjs_collabTicket) — there's no
// PHP here, so this page fakes it in-browser against the SAME dev secret
// the relay was started with. Never do this outside a local demo: the
// whole point of the real flow is that only the authenticated Symfony app
// can mint a valid ticket.
const DEV_SECRET = window.COLLAB_DEMO_SECRET || 'dev-secret';

function base64url(buf) {
    let str = '';
    const bytes = new Uint8Array(buf);
    for (let i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function toHex(buf) {
    return Array.from(new Uint8Array(buf)).map((b) => b.toString(16).padStart(2, '0')).join('');
}

async function mintDevTicket(room, user) {
    const payload = { uid: user.id, name: user.name, avatar: null, color: user.color, room, exp: Math.floor(Date.now() / 1000) + 300 };
    const payloadB64 = base64url(new TextEncoder().encode(JSON.stringify(payload)));

    const key = await crypto.subtle.importKey('raw', new TextEncoder().encode(DEV_SECRET), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
    const sig = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(payloadB64));

    return payloadB64 + '.' + toHex(sig);
}

function renderPresence(el, provider, selfClientId) {
    el.innerHTML = '';
    provider.awareness.getStates().forEach((state, clientId) => {
        if (!state.user) return;
        const badge = document.createElement('span');
        badge.className = 'presence-badge' + (clientId === selfClientId ? ' presence-badge--self' : '');
        badge.style.background = state.user.color;
        badge.textContent = state.user.name + (clientId === selfClientId ? ' (you)' : '');
        el.appendChild(badge);
    });
}

function renderMessages(el, yarray) {
    el.innerHTML = '';
    yarray.toArray().forEach((entry) => {
        const line = document.createElement('div');
        line.className = 'message';
        line.innerHTML = '<span class="message__author" style="color:' + entry.color + '">' + entry.author + '</span> ' + entry.text;
        el.appendChild(line);
    });
    el.scrollTop = el.scrollHeight;
}

async function createPane(paneEl, user) {
    const statusEl = paneEl.querySelector('.status');
    const presenceEl = paneEl.querySelector('.presence');
    const messagesEl = paneEl.querySelector('.messages');
    const inputEl = paneEl.querySelector('.input');
    const sendEl = paneEl.querySelector('.send');

    const ydoc = new Y.Doc();
    const yarray = ydoc.getArray('messages');

    const ticket = await mintDevTicket(ROOM, user);
    const wsBase = WS_URL.replace(/\/$/, '') + '/collab/';

    const provider = new WebsocketProvider(wsBase, encodeURIComponent(ROOM), ydoc, {
        params: { ticket },
    });

    provider.awareness.setLocalStateField('user', { name: user.name, color: user.color });

    provider.on('status', (e) => { statusEl.textContent = e.status; statusEl.className = 'status status--' + e.status; });
    provider.awareness.on('change', () => renderPresence(presenceEl, provider, ydoc.clientID));
    yarray.observe(() => renderMessages(messagesEl, yarray));

    sendEl.addEventListener('click', () => {
        const text = inputEl.value.trim();
        if (!text) return;
        yarray.push([{ author: user.name, color: user.color, text }]);
        inputEl.value = '';
    });
    inputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') sendEl.click(); });

    renderPresence(presenceEl, provider, ydoc.clientID);
    renderMessages(messagesEl, yarray);
}

document.querySelectorAll('.pane').forEach((paneEl) => {
    const name = paneEl.dataset.name;
    const color = paneEl.dataset.color;
    createPane(paneEl, { id: name, name, color });
});
