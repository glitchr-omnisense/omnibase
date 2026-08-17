import $ from 'jquery';
import EditorJs from '@editorjs/editorjs';
import Embed from '@editorjs/embed';
import Warning from '@editorjs/warning';
import NestedList from '@editorjs/nested-list';
import Checklist from '@editorjs/checklist';
import Alert from 'editorjs-alert';
import Table from '@editorjs/table';
import Marker from '@editorjs/marker';
import InlineCode from '@editorjs/inline-code';
import Underline from '@editorjs/underline';
import CodeTool from 'editorjs-code-highlight';
import Quote from '@editorjs/quote';
// import Undo from 'editorjs-undo';

import Header from 'editorjs-header';
import Paragraph from 'editorjs-paragraph';
import Mention from 'editorjs-mention';
import {ImageTool, ImageToolTune} from 'editorjs-image';
import EditorYjs from 'editorjs-yjs';

function json_decode(str) {
    try {
        return JSON.parse(str);
    } catch (e) {
        return undefined;
    }
}

function randid(length)
{
    let result = '';
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;
    let counter = 0;
    while (counter < length) {
      result += characters.charAt(Math.floor(Math.random() * charactersLength));
      counter += 1;
    }
    return result;
}

// ── Optional debounced autosave + conflict guard ─────────────────────────────
// No WebSocket relay involved here — plain POSTs to ux_editorjs_autosave,
// gated per-field by EditorType's "collab_autosave" option (off by default).
// See Base\Controller\UX\EditorController::Autosave() for the server side.
function collabAutosave(editor, holder, collab) {
    if (!collab || !collab.autosave) return null;

    var state = { version: collab.version || null, timer: null, pending: false, banner: null };

    function clearBanner() {
        if (state.banner) { state.banner.remove(); state.banner = null; }
    }

    // Minimal, self-contained conflict UI (no translation catalog wired in
    // yet — plain text, revisit once this ships beyond a first pass).
    function showConflict(remoteBlocks, remoteVersion) {
        clearBanner();

        var banner = document.createElement("div");
        banner.className = "collab-conflict-banner";

        var text = document.createElement("span");
        text.className = "collab-conflict-banner__text";
        text.textContent = "Ce contenu a été modifié par quelqu'un d'autre depuis votre dernière lecture.";

        var restoreBtn = document.createElement("button");
        restoreBtn.type = "button";
        restoreBtn.className = "collab-conflict-banner__restore";
        restoreBtn.textContent = "Restaurer ma version";
        restoreBtn.addEventListener("click", function () {
            // Keep my local content: explicitly re-save on top of the newer
            // server version (deliberate overwrite, user-initiated only).
            state.version = remoteVersion;
            clearBanner();
            doSave();
        });

        var suppressBtn = document.createElement("button");
        suppressBtn.type = "button";
        suppressBtn.className = "collab-conflict-banner__suppress";
        suppressBtn.textContent = "Accepter l'autre version";
        suppressBtn.addEventListener("click", function () {
            // Accept the incoming remote content: replace local blocks and
            // resume autosaving on top of it.
            state.version = remoteVersion;
            clearBanner();
            if (remoteBlocks) editor.render(remoteBlocks);
        });

        banner.appendChild(text);
        banner.appendChild(restoreBtn);
        banner.appendChild(suppressBtn);

        holder.parentNode.insertBefore(banner, holder);
        state.banner = banner;
    }

    function scheduleSave() {
        if (state.timer) clearTimeout(state.timer);
        state.timer = setTimeout(doSave, 4000);
    }

    function doSave() {
        if (state.pending) return;
        state.pending = true;

        editor.save().then(function (savedData) {
            var body = JSON.stringify({
                token: collab.token,
                fqcn: collab.fqcn,
                id: collab.id,
                field: collab.field,
                locale: collab.locale,
                value: JSON.stringify(savedData),
                baseVersion: state.version,
            });

            fetch(collab.autosaveUrl, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: body,
            }).then(function (res) {
                return res.json().then(function (json) { return { status: res.status, json: json }; });
            }).then(function (result) {
                state.pending = false;

                if (result.status === 409 && result.json && result.json.conflict) {
                    showConflict(json_decode(result.json.value), result.json.version);
                    return;
                }

                if (result.json && result.json.version) {
                    state.version = result.json.version;
                    clearBanner();
                }
            }).catch(function () {
                state.pending = false;
            });
        });
    }

    return { scheduleSave: scheduleSave };
}

// ── Optional live collaboration (editorjs-yjs) ───────────────────────────────
// Fetches a ticket from ux_editorjs_collabTicket, which is also the only
// place the actual relay WS URL is learned (Base\Service\Collab\
// CollabTicketFactory::getWsUrl()) — so the ticket has to be fetched BEFORE
// the EditorYjs instance (and hence the presence Tune, and hence the
// EditorJs instance itself) can be constructed. Returns a Promise resolving
// to {wsUrl, ticket}, or null if collaboration isn't configured server-side
// (ux_editorjs_collabTicket responds 503 when the relay isn't deployed).
function fetchCollabTicket(collab) {
    return fetch(collab.ticketUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ token: collab.token, room: collab.room }),
    }).then(function (res) {
        return res.json().then(function (json) {
            if (!res.ok || !json.success || !json.ticket) return null;
            return { wsUrl: json.wsUrl, ticket: json.ticket };
        });
    }).catch(function () {
        return null;
    });
}

$(window).off("DOMContentLoaded.edjs");
$(window).on("DOMContentLoaded.edjs", function() {

    $("[data-edjs]").each(function() {

        $(this).removeAttr("edjs");
        $(this).attr("id", $(this).attr("id") ?? "editorjs-"+randid(10));

        edjs(undefined, $(this).attr("id"), this.dataset.edjs);
    });
});

function edjs(inputEl, holderId, value = {}, options = {})
{
    var holder = $("#"+holderId)[0] || undefined;
    if(holder == undefined) return;

    holder.innerHTML = ""; // delete existing editorjs instance

    var options  = JSON.parse(holder.getAttribute("data-editor-options")) || {};

    var endpointByFile    = holder.getAttribute("data-editor-upload-file") || undefined;
    var endpointByUrl     = holder.getAttribute("data-editor-upload-url")  || undefined;
    var endpointByUser    = holder.getAttribute("data-editor-endpoint-user")    || undefined;
    var endpointByThread  = holder.getAttribute("data-editor-endpoint-thread")  || undefined;
    var endpointByKeyword = holder.getAttribute("data-editor-endpoint-keyword") || undefined;
    
    var data = json_decode(value);
    if (data) Object.assign(options, {data:data});
    
    var onSave = (savedData) => { if(inputEl != undefined) $(inputEl).val(JSON.stringify(savedData)); }

    if(inputEl == undefined) console.warn("EditorJS in read-only mode (some EventDispatcher .off() may appear)");
    Object.assign(options, {
        readOnly: (inputEl == undefined),
        tools: {

            warning: Warning,

            header: {
                class: Header,
                inlineToolbar: ['link', 'mention'],
            },

            paragraph: {

                class: Paragraph,
                inlineToolbar: true,
            },

            mention: {

                class: Mention,
                config: {

                    typingDelay:1000,
                    endpoints: {
                        'arobase': endpointByUser,
                        'hashtag': endpointByKeyword,
                        'dollar': endpointByThread
                    },
                }
            },
            
            imageTune: ImageToolTune,
            image: {
                class: ImageTool,
                tunes: [ 'imageTune' ],
                config: { 
                    accept: 'image/*',
                    endpoints: {
                        byFile: endpointByFile,
                        byUrl: endpointByUrl
                    },
                }
            },

            alert: Alert,
            underline: Underline,
            code: CodeTool,
            marker: {
                class: Marker,
                shortcut: 'CMD+SHIFT+M',
            },

            list: {
                class: NestedList,
                inlineToolbar: true,
            },

            quote: {
                class: Quote,
                inlineToolbar: true,
            },

            checklist: {
                class: Checklist,
                inlineToolbar: true,
            },

            table: {
                class: Table,
            },

            inlineCode: {
                class: InlineCode,
                shortcut: 'CMD+SHIFT+I',
            },

            embed: Embed,
        }
    });

    var collab = null; // collabAutosave state, see above

    // Live collaboration (editorjs-yjs) needs its ticket fetched — which is
    // also the only place the relay's WS URL is learned — BEFORE the
    // presence Tune (and hence the EditorJs instance itself) can be built,
    // so construction is deferred behind that fetch when collab_live is on.
    function finishConstruction(collabYjs) {

        Object.assign(options, {

            holder  : holderId,
            onReady : () => {
                if(data == undefined && value != '') editor.blocks.renderFromHTML(value);
                // if(inputEl != undefined) new Undo({ editor }); // issue

                if(options.readOnly ?? false) {
                    $("#" + holderId).children(".codex-editor").addClass("read-only");
                }

                if (inputEl != undefined) collab = collabAutosave(editor, holder, options.collab);
                if (collabYjs) collabYjs.attach(editor, holderId);
            },
            onChange: async (api, event) => {

                if(options.readOnly) return;
                editor.save().then(onSave);
                if (collab) collab.scheduleSave();
                if (collabYjs) collabYjs.onChange(api, event);
            }
        });

        var editor = new EditorJs(options);
    }

    if (options.collab && options.collab.live && inputEl != undefined) {
        fetchCollabTicket(options.collab).then(function (result) {
            if (!result) {
                // Relay not configured/reachable — fall back to plain
                // (non-collaborative) editing rather than failing to load.
                finishConstruction(null);
                return;
            }

            var collabYjs = new EditorYjs({
                wsUrl: result.wsUrl,
                room: options.collab.room,
                ticket: result.ticket,
                getTicket: () => fetchCollabTicket(options.collab).then(function (r) { return r ? r.ticket : null; }),
                user: options.collab.user || undefined,
            });

            options.tools.presence = { class: collabYjs.Tune };
            options.tunes = (options.tunes || []).concat(['presence']);

            finishConstruction(collabYjs);
        });
    } else {
        finishConstruction(null);
    }
}

window.addEventListener("load.form_type", function (el) {

    document.querySelectorAll("[data-editor-field]").forEach((function (el) {

        var id    = el.getAttribute("data-editor-field");

        var input = $("#"+id);
        var value = $("#"+id).val();

        var editorId = id+"_editor";
        edjs(input, editorId, value);
    }));
});

// ── EditorJS image caption: empty-state flag (WYSIWYG) ───────────────────────
// CSS `:empty` can't tell that a caption the user cleared still holds a stray
// <br>, so it would treat a blank caption as filled and reserve a box below the
// image. We flag truly-empty captions (by trimmed text) with `caption-empty` so
// the stylesheet can overlay an empty caption ON the image (no layout gap) in the
// editor, hide it in the read-only viewer, and flow a filled one BELOW the image.
// Runs in both contexts because this file loads with both.
(function () {
    function markCaption(el) {
        if (el && el.classList && el.classList.contains('image-tool__caption')) {
            el.classList.toggle('caption-empty', (el.textContent || '').trim() === '');
        }
    }
    function markAll() {
        document.querySelectorAll('.image-tool__caption').forEach(markCaption);
    }
    document.addEventListener('input', function (e) { markCaption(e.target); }, true);
    document.addEventListener('DOMContentLoaded', markAll);
    window.addEventListener('load', markAll);
    if ('MutationObserver' in window) {
        var t;
        new MutationObserver(function () {
            clearTimeout(t);
            t = setTimeout(markAll, 100); // debounced: catch render + programmatic edits
        }).observe(document.documentElement, { subtree: true, childList: true });
    }
})();
