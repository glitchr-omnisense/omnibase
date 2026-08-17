import $ from 'jquery';
import * as Y from 'yjs';
import { WebsocketProvider } from 'y-websocket';

// ── Optional live collaboration for regular fields (input/select/select2) ───
// Reuses the same room/ticket contract as editorjs-yjs's EditorJS binding
// (ux_editorjs_collabTicket, ux_editorjs_autosave), but doesn't need a
// content-sync channel at all — a plain field's value isn't collaboratively
// merged, just its focus state (for the presence badge) and, in "autosave"
// mode, its value on change (with the same conflict guard EditorType uses).
// Each collab-enabled field gets its own room (see CollabRoomResolver), so
// a plain Y.Doc's awareness channel is enough — no Y.Array needed.
//
// Delegated document-level focusin/focusout/change listeners, not
// per-element binding: this is the only approach that survives
// form-type-select2.js's destroy-and-rebuild-on-reload (the whole
// .select2-container is removed and recreated on every load.form_type
// refire) and any other widget's DOM teardown, without needing an
// init-guard — there's nothing to double-bind, and target elements are
// looked up fresh by id at render time rather than cached.

function json_decode(str) {
    try {
        return JSON.parse(str);
    } catch (e) {
        return undefined;
    }
}

function fetchTicket(ticketUrl, token, room) {
    return fetch(ticketUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ token: token, room: room }),
    }).then(function (res) {
        return res.json().then(function (json) {
            if (!res.ok || !json.success || !json.ticket) return null;
            return { wsUrl: json.wsUrl, ticket: json.ticket };
        });
    }).catch(function () {
        return null;
    });
}

// room -> { ydoc, provider, users: [{name,color}], fieldId }
var connections = {};

function connectPresence(fieldId, room, ticketUrl, token, user) {
    if (connections[room]) return;

    var ydoc = new Y.Doc();
    var entry = { ydoc: ydoc, provider: null, users: [], fieldId: fieldId };
    connections[room] = entry;

    fetchTicket(ticketUrl, token, room).then(function (result) {
        if (!result || connections[room] !== entry) return; // superseded/removed meanwhile

        var wsBase = result.wsUrl.replace(/\/+$/, "") + "/collab/";
        var provider = new WebsocketProvider(wsBase, encodeURIComponent(room), ydoc, {
            params: { ticket: result.ticket },
        });
        provider.awareness.setLocalStateField("user", user);
        entry.provider = provider;

        provider.awareness.on("change", function () {
            entry.users = [];
            provider.awareness.getStates().forEach(function (state, clientId) {
                if (clientId === ydoc.clientID) return; // never badge yourself
                if (state.focused && state.user) entry.users.push(state.user);
            });
            renderBadge(entry);
        });
    });
}

function renderBadge(entry) {
    var field = document.getElementById(entry.fieldId);
    if (!field) return;

    var badgeId = entry.fieldId + "_collab_badge";
    var badge = document.getElementById(badgeId);

    if (entry.users.length === 0) {
        field.classList.remove("collab-field--focused");
        if (badge) badge.remove();
        return;
    }

    field.classList.add("collab-field--focused");
    field.style.setProperty("--collab-color", entry.users[0].color || "#3a9bd9");

    if (!badge) {
        badge = document.createElement("span");
        badge.id = badgeId;
        badge.className = "collab-field-badge";
        field.parentNode.insertBefore(badge, field.nextSibling);
    }
    badge.innerHTML = "";
    entry.users.forEach(function (u) {
        var el = document.createElement("span");
        el.className = "collab-field-badge__name";
        el.style.background = u.color || "#3a9bd9";
        el.textContent = u.name || "?";
        badge.appendChild(el);
    });
}

// ── "autosave" mode: same debounced-save + conflict-guard shape as
// form-type-editor.js's collabAutosave(), reused for a plain field value
// instead of EditorJS block JSON. ──────────────────────────────────────────
var autosaveState = {}; // fieldId -> {version, pending, banner}

function autosaveField(field) {
    var fieldId = field.id;
    var state = autosaveState[fieldId] || (autosaveState[fieldId] = { version: null, pending: false, banner: null });

    function clearBanner() {
        if (state.banner) { state.banner.remove(); state.banner = null; }
    }

    function showConflict(remoteValue, remoteVersion) {
        clearBanner();

        var banner = document.createElement("div");
        banner.className = "collab-conflict-banner";

        var text = document.createElement("span");
        text.className = "collab-conflict-banner__text";
        text.textContent = "Ce contenu a été modifié par quelqu'un d'autre depuis votre dernière lecture.";

        var restoreBtn = document.createElement("button");
        restoreBtn.type = "button";
        restoreBtn.textContent = "Restaurer ma version";
        restoreBtn.addEventListener("click", function () {
            state.version = remoteVersion;
            clearBanner();
            doSave();
        });

        var suppressBtn = document.createElement("button");
        suppressBtn.type = "button";
        suppressBtn.textContent = "Accepter l'autre version";
        suppressBtn.addEventListener("click", function () {
            state.version = remoteVersion;
            field.value = remoteValue;
            clearBanner();
        });

        banner.appendChild(text);
        banner.appendChild(restoreBtn);
        banner.appendChild(suppressBtn);

        field.parentNode.insertBefore(banner, field.nextSibling);
        state.banner = banner;
    }

    function doSave() {
        if (state.pending) return;
        state.pending = true;

        fetch(field.dataset.collabAutosaveUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
                token: field.dataset.collabToken,
                fqcn: field.dataset.collabFqcn,
                id: field.dataset.collabId,
                field: field.dataset.collabProperty,
                locale: field.dataset.collabLocale || null,
                value: field.value,
                baseVersion: state.version,
            }),
        }).then(function (res) {
            return res.json().then(function (json) { return { status: res.status, json: json }; });
        }).then(function (result) {
            state.pending = false;

            if (result.status === 409 && result.json && result.json.conflict) {
                showConflict(result.json.value, result.json.version);
                return;
            }

            if (result.json && result.json.version) {
                state.version = result.json.version;
                clearBanner();
            }
        }).catch(function () {
            state.pending = false;
        });
    }

    doSave();
}

// Delegated via jQuery, not native addEventListener, specifically because
// select2 needs its own "select2:open"/"select2:close" custom events —
// select2 replaces a field's visible UI with sibling DOM elements (the
// original <select data-collab-field> stays hidden), so a click/focus on
// select2's own UI never bubbles a native focusin *through* the tagged
// element the way it would for a plain <input> — closest() would never
// find it. select2 fires its custom events directly on the original
// select (confirmed against form-type-select2.js's own `$(field).select2
// (...).on("select2:open", ...)` binding), and jQuery's delegated .on()
// handles both native event bubbling (plain inputs) and this custom-event
// case uniformly, so one set of handlers covers both.

function markFocused(field, focused) {
    var room = field.dataset.collabRoom;
    if (!room) return;

    if (focused && field.dataset.collabMode === "live") {
        var user = json_decode(field.dataset.collabUser || "");
        connectPresence(field.id, room, field.dataset.collabTicketUrl, field.dataset.collabToken, user || { name: "?", color: "#999" });
    }

    var entry = connections[room];
    if (!entry) return;

    if (entry.provider) {
        entry.provider.awareness.setLocalStateField("focused", focused);
    } else if (focused) {
        // Ticket fetch still in flight — flag once the provider exists.
        var check = setInterval(function () {
            if (entry.provider) { entry.provider.awareness.setLocalStateField("focused", true); clearInterval(check); }
        }, 100);
    }
}

$(document)
    .on("focusin select2:open", "[data-collab-field]", function () {
        markFocused(this, true);
    })
    .on("focusout select2:close", "[data-collab-field]", function () {
        markFocused(this, false);
        if (this.dataset.collabMode === "autosave") autosaveField(this);
    })
    .on("change", "[data-collab-field]", function () {
        if (this.dataset.collabMode === "autosave") autosaveField(this);
    });
