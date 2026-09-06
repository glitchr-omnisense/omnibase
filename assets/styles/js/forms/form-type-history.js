// Per-field modification history (the clock badge next to a field label).
//
// Restoring a value NEVER writes anything. The old value is fetched, put into
// the form input, and left there: the editor saves the form like any other
// change, so the restore goes through validation and the normal update path,
// and - because it is itself a change - it produces its own revision and can
// be undone the same way.
//
// Widgets that hold their value somewhere other than the input (EditorJS,
// select2) cannot be set by assigning `.value`. Rather than teach this file
// about each of them, it dispatches a cancelable `restore.form_type` event and
// lets the widget's own module claim it with preventDefault(); anything left
// unclaimed falls through to the plain input/textarea/select path below.

import $ from 'jquery';

function closeAll(except) {
    document.querySelectorAll("[data-field-history]").forEach(function (host) {
        if (host === except) return;
        var panel = host.querySelector(".field-history-panel");
        var toggle = host.querySelector(".field-history-toggle");
        if (panel) panel.hidden = true;
        if (toggle) toggle.setAttribute("aria-expanded", "false");
    });
}

function flash(host, message, ok) {
    var note = host.querySelector(".field-history-note");
    if (!note) {
        note = document.createElement("p");
        note.className = "field-history-note";
        var panel = host.querySelector(".field-history-panel");
        if (panel) panel.appendChild(note);
    }
    note.textContent = message;
    note.classList.toggle("is-error", !ok);
}

// The control a field's id actually stands for.
//
// form.vars.id names the form NODE, which is not always the form control. A
// select2 association renders its <select> as "<id>_choice" and leaves nothing
// at "<id>" at all, so looking the id up directly finds nothing and the
// restore reports failure on exactly the fields the select2 setter exists for.
// The _editor suffix is deliberately NOT resolved here: a wysiwyg field's
// input DOES carry the plain id, and its own handler wants that input.
function resolveTarget(id) {

    var el = document.getElementById(id);
    if (el && /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)) return el;

    var choice = document.getElementById(id + "_choice");
    if (choice) return choice;

    // Last resort: the first control inside whatever that id does name.
    return (el && el.querySelector("input, select, textarea")) || el;
}

// The generic setter. Returns false when there is nothing it can set, so the
// caller can say so instead of silently doing nothing.
function applyGeneric(target, value) {
    if (!target) return false;

    if (target.tagName === "SELECT") {
        var wanted = Array.isArray(value)
            ? value.map(function (v) { return String(v && v.id !== undefined ? v.id : v); })
            : [String(value && value.id !== undefined ? value.id : value)];

        Array.prototype.forEach.call(target.options, function (option) {
            option.selected = wanted.indexOf(String(option.value)) !== -1;
        });
    } else if (target.type === "checkbox") {
        target.checked = !!value;
    } else {
        target.value = value === null || value === undefined ? "" : String(value);
    }

    // Both events, and jQuery's too: this form stack listens through all three
    // depending on the widget (native listeners, jQuery .on("change"), and the
    // dirty-state tracking that decides whether leaving the page warns).
    target.dispatchEvent(new Event("input", { bubbles: true }));
    target.dispatchEvent(new Event("change", { bubbles: true }));
    try { $(target).trigger("change"); } catch (e) { /* jQuery optional */ }

    return true;
}

function restore(host, revisionId, key) {
    var url = host.getAttribute("data-field-history-url");
    var targetId = host.getAttribute("data-field-history-target");
    if (!url || !targetId) return;

    var endpoint = url.replace("__ID__", encodeURIComponent(revisionId))
        + (url.indexOf("?") === -1 ? "?" : "&") + "field=" + encodeURIComponent(key);

    fetch(endpoint, { headers: { "Accept": "application/json" }, credentials: "same-origin" })
        .then(function (response) {
            if (!response.ok) throw new Error("HTTP " + response.status);
            return response.json();
        })
        .then(function (payload) {
            var target = resolveTarget(targetId);

            var claimed = !window.dispatchEvent(new CustomEvent("restore.form_type", {
                cancelable: true,
                detail: { id: targetId, target: target, value: payload.value, key: key }
            }));

            var done = claimed || applyGeneric(target, payload.value);

            flash(host, host.getAttribute("data-message-restored") || "Restored", done);
            if (done) {
                host.classList.add("is-restored");
                var panel = host.querySelector(".field-history-panel");
                if (panel) panel.hidden = true;
                var toggle = host.querySelector(".field-history-toggle");
                if (toggle) toggle.setAttribute("aria-expanded", "false");
            }
        })
        .catch(function () {
            flash(host, host.getAttribute("data-message-failed") || "Failed", false);
        });
}

function bind(host) {
    if (host.dataset.fieldHistoryBound === "1") return;
    host.dataset.fieldHistoryBound = "1";

    var toggle = host.querySelector(".field-history-toggle");
    var panel = host.querySelector(".field-history-panel");
    if (!toggle || !panel) return;

    toggle.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();

        var opening = panel.hidden;
        closeAll(host);
        panel.hidden = !opening;
        toggle.setAttribute("aria-expanded", opening ? "true" : "false");

        // A badge sitting near the right edge of a narrow column would push
        // its panel off screen; flip it to open leftwards instead.
        if (opening) {
            panel.classList.remove("is-flipped");
            if (panel.getBoundingClientRect().right > document.documentElement.clientWidth - 8) {
                panel.classList.add("is-flipped");
            }
        }
    });

    panel.addEventListener("click", function (event) {
        var entry = event.target.closest(".field-history-entry");
        if (!entry || entry.disabled) return;

        event.preventDefault();
        event.stopPropagation();
        restore(host, entry.getAttribute("data-revision"), entry.getAttribute("data-key"));
    });
}

function bindAll() {
    document.querySelectorAll("[data-field-history]").forEach(bind);
}

document.addEventListener("click", function () { closeAll(null); });
document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeAll(null);
});

// Same double hook as the other form-type modules: load.form_type fires again
// whenever a lazy collection injects new rows, and dataset.fieldHistoryBound
// keeps a re-fire from stacking a second set of listeners on the same badge.
window.addEventListener("load.form_type", bindAll);
window.addEventListener("load", bindAll);
