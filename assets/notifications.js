/*
 * Notification center client - the bell in the front-end toolbar and in the
 * back-office share it. Encore entry "notifications" (base-bundle build).
 *
 * Everything hangs off data attributes, so a template only has to render:
 *   [data-notifications]            the root, with data-csrf, data-read-url,
 *                                   data-latest-url, data-push-*-url
 *   [data-notifications-badge]      the red dot / count (hidden when 0)
 *   [data-notifications-toggle]     the bell button (opens the panel)
 *   [data-notifications-panel]      the dropdown
 *   [data-notifications-read-all]   "mark all as read"
 *   [data-notification-item]        one entry, with data-id (+ .is-unread)
 *   [data-push-toggle]              enable/disable push in this browser
 *
 * Idempotent on purpose: the public site is a transparent.js SPA that
 * re-dispatches `load` on every navigation and swaps #page's subtree, so
 * this binds delegated handlers on document exactly once and re-syncs the
 * push button state whenever the markup is replaced.
 */
(function () {
    if (window.__baseNotificationsBound) { window.__baseNotificationsBound.sync(); return; }

    var SW_URL = '/sw.js';

    function root() { return document.querySelector('[data-notifications]'); }
    function cfg(name) { var r = root(); return r ? r.getAttribute('data-' + name) : null; }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ token: cfg('csrf') }, body || {}))
        }).then(function (res) { return res.json().catch(function () { return {}; }).then(function (json) { json.__status = res.status; return json; }); });
    }

    function setUnread(count) {
        document.querySelectorAll('[data-notifications-badge]').forEach(function (badge) {
            badge.textContent = count > 9 ? '9+' : String(count);
            badge.hidden = !(count > 0);
        });
        document.querySelectorAll('[data-notifications-toggle]').forEach(function (btn) {
            btn.classList.toggle('has-unread', count > 0);
        });
    }

    function markAllRead() {
        var url = cfg('read-url');
        if (!url) return Promise.resolve();
        return post(url).then(function (json) {
            if (json.success) {
                setUnread(json.unread || 0);
                document.querySelectorAll('[data-notification-item].is-unread').forEach(function (el) { el.classList.remove('is-unread'); });
            }
        });
    }

    function markRead(id) {
        var url = cfg('read-url');
        if (!url) return Promise.resolve();
        return post(url, { id: id }).then(function (json) {
            if (json.success) {
                setUnread(json.unread || 0);
                var el = document.querySelector('[data-notification-item][data-id="' + id + '"]');
                if (el) el.classList.remove('is-unread');
            }
        });
    }

    // ── Web Push ────────────────────────────────────────────────────────
    function pushSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
        return out;
    }

    function registration() {
        return navigator.serviceWorker.register(SW_URL, { scope: '/' }).then(function () { return navigator.serviceWorker.ready; });
    }

    function currentSubscription() {
        if (!pushSupported()) return Promise.resolve(null);
        return navigator.serviceWorker.getRegistration('/').then(function (reg) {
            return reg ? reg.pushManager.getSubscription() : null;
        }).catch(function () { return null; });
    }

    function enablePush() {
        var publicKey = cfg('push-key');
        if (!pushSupported() || !publicKey) return Promise.reject(new Error('unsupported'));
        return Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') throw new Error('denied');
            return registration();
        }).then(function (reg) {
            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(publicKey) });
        }).then(function (sub) {
            return post(cfg('push-subscribe-url'), { subscription: sub.toJSON() }).then(function (json) {
                if (!json.success) throw new Error('server');
                return sub;
            });
        });
    }

    function disablePush() {
        return currentSubscription().then(function (sub) {
            if (!sub) return null;
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () { return post(cfg('push-unsubscribe-url'), { endpoint: endpoint }); });
        });
    }

    function syncPushButtons() {
        var buttons = document.querySelectorAll('[data-push-toggle]');
        if (!buttons.length) return;
        if (!pushSupported() || !cfg('push-key')) {
            buttons.forEach(function (b) { b.hidden = true; });
            return;
        }
        currentSubscription().then(function (sub) {
            var on = !!sub && Notification.permission === 'granted';
            buttons.forEach(function (b) {
                b.hidden = false;
                b.disabled = Notification.permission === 'denied';
                b.classList.toggle('is-on', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                var label = on ? b.getAttribute('data-label-on') : b.getAttribute('data-label-off');
                if (Notification.permission === 'denied') label = b.getAttribute('data-label-denied') || label;
                var text = b.querySelector('[data-push-label]') || b;
                if (label) text.textContent = label;
            });
        });
    }

    // ── Panel ───────────────────────────────────────────────────────────
    function closePanels(except) {
        document.querySelectorAll('[data-notifications-panel].is-open').forEach(function (p) {
            if (p !== except) { p.classList.remove('is-open'); p.hidden = true; }
        });
    }

    function openPanel(panel) {
        panel.hidden = false;
        panel.classList.add('is-open');
        // Opening the list is reading it: the dot goes away, entries keep
        // their own unread styling until clicked.
        if (document.querySelector('[data-notification-item].is-unread')) markAllRead();
        else setUnread(0);
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-notifications-toggle]');
        if (toggle) {
            e.preventDefault();
            var panel = document.querySelector(toggle.getAttribute('data-notifications-toggle') || '[data-notifications-panel]');
            if (!panel) return;
            if (panel.classList.contains('is-open')) { closePanels(); } else { closePanels(panel); openPanel(panel); }
            return;
        }

        var readAll = e.target.closest('[data-notifications-read-all]');
        if (readAll) { e.preventDefault(); markAllRead(); return; }

        var pushToggle = e.target.closest('[data-push-toggle]');
        if (pushToggle) {
            e.preventDefault();
            pushToggle.disabled = true;
            var wasOn = pushToggle.classList.contains('is-on');
            (wasOn ? disablePush() : enablePush()).catch(function (err) {
                if (err && err.message === 'denied') return;
                console.warn('notifications: push toggle failed', err);
            }).then(function () { pushToggle.disabled = false; syncPushButtons(); });
            return;
        }

        var item = e.target.closest('[data-notification-item]');
        if (item && item.classList.contains('is-unread')) {
            markRead(item.getAttribute('data-id'));
            // let the link navigate
            return;
        }

        if (!e.target.closest('[data-notifications-panel]')) closePanels();
    });

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePanels(); });

    function sync() {
        syncPushButtons();
        var badge = document.querySelector('[data-notifications-badge]');
        if (badge) setUnread(parseInt(badge.getAttribute('data-count') || badge.textContent, 10) || 0);
    }

    // A tab that sat in the background comes back with a stale badge; the
    // latest endpoint is cheap, and focus is a user-driven rate.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible') return;
        var url = cfg('latest-url');
        if (!url) return;
        fetch(url + '?limit=1', { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; })
            .then(function (json) { if (json) setUnread(json.unread || 0); }).catch(function () {});
    });

    window.addEventListener('load', sync);
    if (document.readyState === 'complete') sync();

    window.__baseNotificationsBound = { sync: sync, enablePush: enablePush, disablePush: disablePush, markAllRead: markAllRead };
})();
