import $ from 'jquery';

// yjs/y-websocket are loaded lazily (dynamic import), not as a static
// top-level import, and specifically NOT because of bundle size alone.
// This file lives in the always-loaded form-defer.js entry, while
// editorjs-yjs (form-type-editor.js's lazy form-defer.editor.js entry)
// also depends on yjs - Encore builds each entry as an independent
// bundle with no shared-chunk config between them, so BOTH ended up
// carrying their own copy of yjs's module code, and yjs's own
// module-identity check logged "Yjs was already imported..." the moment
// both entries were present on the same page (confirmed live on an
// article edit page). Since collab_live/collab isn't turned on for any
// field yet, neither copy's module-level code needs to run AT ALL on a
// page that never actually asks for it - loading it only inside
// connectPresence(), the one place it's used, means yjs's side effects
// never fire unless a live-collab field is genuinely present, sidestepping
// the duplicate-registration entirely for every page today.
var yjsModules = null;
function loadYjs() {
    if (!yjsModules) {
        yjsModules = Promise.all([import('yjs'), import('y-websocket')]);
    }
    return yjsModules;
}

// ── Optional live collaboration for regular fields (input/select/select2) ───
// This code reuses the same room contract and ticket contract as
// editorjs-yjs's EditorJS binding. This code uses the
// ux_editorjs_collabTicket action and the ux_editorjs_autosave action.
// This code needs no content-sync channel. A plain field's value has no
// collaborative merge. This code tracks only two things: the field's
// focus state, for the presence badge, and, in "autosave" mode, the
// field's value on a change event, with the same conflict guard that
// EditorType uses. Each field with an active collab option receives a
// separate room. Refer to CollabRoomResolver for the room-key format.
// Because of this separation, a plain Y.Doc's awareness channel is
// sufficient. This code needs no Y.Array.
//
// This code uses delegated, document-level listeners for the focusin
// event, the focusout event, and the change event. This code does not
// bind a listener to each element directly. This method is the only
// method that survives two conditions. First condition:
// form-type-select2.js destroys and rebuilds a field on each reload. This
// process removes and recreates the whole .select2-container element, on
// each load.form_type event. Second condition: another widget can remove
// a field's DOM element completely. This delegated method needs no
// init-guard, because this method binds nothing twice. This method finds
// each target element fresh, by id, at render time. This method does not
// use a cached reference.

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

// This object maps each room to a connection record, with this format:
// { ydoc, provider, users: [{name,color}], fieldId }.
var connections = {};

function connectPresence(fieldId, room, ticketUrl, token, user) {
    if (connections[room]) return;

    var entry = { ydoc: null, provider: null, users: [], fieldId: fieldId };
    connections[room] = entry;

    Promise.all([loadYjs(), fetchTicket(ticketUrl, token, room)]).then(function (all) {
        var Y = all[0][0];
        var WebsocketProvider = all[0][1].WebsocketProvider;
        var result = all[1];

        // A newer request can replace this entry, or a caller can remove
        // this entry, before this resolves. In that case, this code
        // stops here.
        if (!result || connections[room] !== entry) return;

        var ydoc = new Y.Doc();
        entry.ydoc = ydoc;

        var wsBase = result.wsUrl.replace(/\/+$/, "") + "/collab/";
        var provider = new WebsocketProvider(wsBase, encodeURIComponent(room), ydoc, {
            params: { ticket: result.ticket },
        });
        provider.awareness.setLocalStateField("user", user);
        entry.provider = provider;

        provider.awareness.on("change", function () {
            entry.users = [];
            provider.awareness.getStates().forEach(function (state, clientId) {
                // This code never shows a badge for the local user.
                if (clientId === ydoc.clientID) return;
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

// ── "autosave" mode ───────────────────────────────────────────────────────
// This code uses the same debounced-save shape and the same
// conflict-guard shape as form-type-editor.js's collabAutosave()
// function. This code applies that shape to a plain field value, instead
// of EditorJS block JSON data.

// This object maps each field id to a state record, with this format:
// {version, pending, banner}.
var autosaveState = {};

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

// This code uses jQuery delegation, not native addEventListener. This
// method is necessary for one specific reason: select2 needs its own
// "select2:open" event and "select2:close" event. select2 replaces a
// field's visible UI with separate, sibling DOM elements. The original
// element, <select data-collab-field>, stays hidden. Because of this
// structure, a click or a focus action on select2's own UI never
// triggers a native focusin event through the tagged element, in the way
// that a plain <input> element would. A closest() call would never find
// the tagged element in this case. select2 sends its custom events
// directly to the original select element. This behavior is confirmed
// against form-type-select2.js's own binding:
// `$(field).select2(...).on("select2:open", ...)`. jQuery's delegated
// .on() method handles both cases with one set of handlers: native
// event bubbling, for plain input elements, and this custom-event
// method, for select2 elements.

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
        // The ticket fetch is still active. This code sets the flag
        // after the provider object exists.
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
