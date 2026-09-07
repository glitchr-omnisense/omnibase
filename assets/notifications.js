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
 *   [data-notifications-delete-read] "delete every read one"
 *   [data-notification-read-toggle] per entry: flips read/unread (data-id)
 *   [data-notification-delete]      per entry: deletes it (data-id)
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

    // Optimistic: the row changes on the click, the request follows, and the
    // row is put back only if the server refuses. A round trip on this site
    // is a good fraction of a second, which read as "the icon lags".
    function markRead(id) {
        var url = cfg('read-url');
        if (!url) return Promise.resolve();
        setReadState(id, true);
        return post(url, { id: id }).then(function (json) {
            if (json.success) setUnread(json.unread || 0);
            else setReadState(id, false);
        }).catch(function () { setReadState(id, false); });
    }

    function setReadState(id, isRead) {
        document.querySelectorAll('[data-notification-item][data-id="' + id + '"]').forEach(function (el) {
            el.classList.toggle('is-unread', !isRead);
            el.querySelectorAll('[data-notification-read-toggle]').forEach(function (b) {
                b.classList.toggle('is-read', isRead);
                b.setAttribute('title', b.getAttribute(isRead ? 'data-label-unread' : 'data-label-read') || '');
                b.setAttribute('aria-label', b.getAttribute('title'));
            });
        });
    }

    function toggleRead(id, read) {
        var url = cfg('read-url');
        if (!url) return Promise.resolve();
        setReadState(id, read);
        return post(url, { id: id, read: read }).then(function (json) {
            if (json.success) { setUnread(json.unread || 0); setReadState(id, json.isRead !== false); }
            else setReadState(id, !read);
        }).catch(function () { setReadState(id, !read); });
    }

    function removeEntries(ids) {
        ids.forEach(function (id) {
            document.querySelectorAll('[data-notification-item][data-id="' + id + '"]').forEach(function (el) {
                var row = el.closest('li') || el;
                row.classList.add('is-leaving');
                setTimeout(function () { row.remove(); }, 200);
            });
        });
    }

    function deleteOne(id) {
        var url = cfg('delete-url');
        if (!url) return Promise.resolve();
        return post(url, { id: id }).then(function (json) {
            if (json.success) { setUnread(json.unread || 0); removeEntries(json.deleted || [id]); }
        });
    }

    function deleteRead() {
        var url = cfg('delete-url');
        if (!url) return Promise.resolve();
        return post(url).then(function (json) {
            if (json.success) { setUnread(json.unread || 0); removeEntries(json.deleted || []); }
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
    // A panel marked data-notifications-float is lifted out of wherever the
    // template rendered it (the front-end toolbar, inside a masked, scrolling
    // sidebar column) into <body> the first time it opens, and positioned
    // fixed under its bell. That frees it from every ancestor: the column's
    // overflow and fade mask, the toolbar's icon-button CSS, the sidebar's
    // own scroll. It gets its own scrolling list and a height that follows
    // the viewport instead. Panels without the flag (the back-office topbar,
    // which already has a proper dropdown) keep their in-place behaviour.
    function isFloating(panel) { return panel.hasAttribute('data-notifications-float'); }

    function closePanels(except) {
        document.querySelectorAll('[data-notifications-panel].is-open').forEach(function (p) {
            if (p !== except) { p.classList.remove('is-open'); p.hidden = true; }
        });
    }

    // transparent.js replaces #page on every navigation, so a panel lifted to
    // <body> would outlive its page and sit beside the new page's own panel.
    // The new page's panel is what the bell finds (document order), and any
    // older lifted panel is dropped the next time one opens.
    function dropStaleFloating(keep) {
        document.querySelectorAll('body > [data-notifications-panel]').forEach(function (p) {
            if (p !== keep && !document.querySelector('#page') ?.contains(p)) p.remove();
        });
    }

    function placeFloating(panel, anchor) {
        var margin = 8;
        var vw = window.innerWidth, vh = window.innerHeight;
        // Aligned with the toolbar's button row (the bell's <ul>), the way the
        // language menu sits under it, and at least as wide as that row.
        var row = anchor ? (anchor.closest('ul') || anchor) : null;
        var a = anchor ? anchor.getBoundingClientRect() : { left: margin, right: margin, bottom: margin, top: margin };
        var rowRect = row ? row.getBoundingClientRect() : a;
        var width = Math.min(Math.max(rowRect.width, 300), 420, vw - 2 * margin);
        var left = rowRect.left;
        if (left + width > vw - margin) left = vw - margin - width;
        if (left < margin) left = margin;
        var top = a.bottom + 6;
        // Room kept at the bottom of the window: the browser's link preview
        // sits there and was covering the panel's last row on layout1.
        var bottomGap = 48;
        var maxH = vh - top - bottomGap;
        if (maxH < 240 && a.top > vh / 2) {
            // Not enough room below a bell that sits low: open upwards.
            maxH = Math.max(240, a.top - 6 - margin);
            top = Math.max(margin, a.top - 6 - maxH);
        }
        // A menu, not a page: about five entries tall, the list scrolls for
        // the rest (data-notifications-max-height on the panel overrides).
        var cap = parseInt(panel.getAttribute('data-notifications-max-height') || '440', 10);
        if (cap > 0) maxH = Math.min(maxH, cap);
        panel.style.position = 'fixed';
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
        panel.style.width = width + 'px';
        panel.style.maxWidth = 'none';
        panel.style.maxHeight = maxH + 'px';
        panel.style.zIndex = '2000';
    }

    // Fit the panel inside whatever clips it. The front-end sidebar's toolbar
    // is wider than the scrolling column it sits in (its search bar sets the
    // width), so a panel sized to the toolbar lost its right third behind
    // the column's edge. Measured at open time against the nearest ancestor
    // that clips (overflow other than visible), so it holds for every layout
    // and breakpoint without knowing any of their widths.
    function fitPanel(panel) {
        panel.style.maxWidth = '';
        var el = panel.parentElement;
        while (el && el !== document.body) {
            var o = getComputedStyle(el).overflow + getComputedStyle(el).overflowX;
            if (/hidden|scroll|auto|clip/.test(o)) break;
            el = el.parentElement;
        }
        if (!el || el === document.body) return;
        var room = el.getBoundingClientRect().right - panel.getBoundingClientRect().left - 4;
        if (room > 120 && room < panel.getBoundingClientRect().width) panel.style.maxWidth = Math.floor(room) + 'px';
    }

    var lastAnchor = null;
    function openPanel(panel, anchor) {
        lastAnchor = anchor || lastAnchor;
        if (isFloating(panel)) {
            dropStaleFloating(panel);
            if (panel.parentElement !== document.body) document.body.appendChild(panel);
            panel.classList.add('is-floating');
            placeFloating(panel, lastAnchor);
        }
        panel.hidden = false;
        panel.classList.add('is-open');
        if (!isFloating(panel)) fitPanel(panel);
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
            if (panel.classList.contains('is-open')) { closePanels(); } else { closePanels(panel); openPanel(panel, toggle); }
            return;
        }

        var readAll = e.target.closest('[data-notifications-read-all]');
        if (readAll) { e.preventDefault(); markAllRead(); return; }

        var deleteReadBtn = e.target.closest('[data-notifications-delete-read]');
        if (deleteReadBtn) { e.preventDefault(); deleteRead(); return; }

        var readToggle = e.target.closest('[data-notification-read-toggle]');
        if (readToggle) {
            e.preventDefault(); e.stopPropagation();
            var entry = readToggle.closest('[data-notification-item]');
            if (entry) toggleRead(entry.getAttribute('data-id'), entry.classList.contains('is-unread'));
            return;
        }

        var deleteBtn = e.target.closest('[data-notification-delete]');
        if (deleteBtn) {
            e.preventDefault(); e.stopPropagation();
            var target = deleteBtn.closest('[data-notification-item]');
            if (target) deleteOne(target.getAttribute('data-id'));
            return;
        }

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

    // A floating panel is pinned to where the bell WAS: page scroll and
    // resize move the bell, so re-place (resize) or dismiss (scroll) rather
    // than leave it stranded. Scrolling inside the panel's own list does not
    // reach the window, so it is unaffected.
    window.addEventListener('resize', function () {
        var open = document.querySelector('[data-notifications-panel].is-open.is-floating');
        if (open && lastAnchor && document.contains(lastAnchor)) placeFloating(open, lastAnchor);
    });
    // Page scroll follows the bell instead of dismissing: the toolbars are
    // fixed on every layout so the bell rarely moves, and losing the panel to
    // a wheel gesture that merely reached the end of its list was the first
    // thing reported about it.
    window.addEventListener('scroll', function () {
        var open = document.querySelector('[data-notifications-panel].is-open.is-floating');
        if (open && lastAnchor && document.contains(lastAnchor)) placeFloating(open, lastAnchor);
    }, { passive: true });

    // ── Toasts ──────────────────────────────────────────────────────────
    // Transient cards for what arrived since this browser last looked:
    // compared against a per-browser "seen" list of notification ids, so a
    // notification is toasted once, on whichever page the person is on
    // when it is first noticed (load, SPA navigation, tab focus). Never on
    // a page where the person is not signed in (no root).
    var SEEN_KEY = 'base/notifications/seen';
    function seenIds() { try { return JSON.parse(localStorage.getItem(SEEN_KEY) || '[]'); } catch (e) { return []; } }
    function rememberSeen(ids) { try { localStorage.setItem(SEEN_KEY, JSON.stringify(ids.slice(-200))); } catch (e) {} }

    function toastContainer() {
        var c = document.getElementById('notifications-toasts');
        if (!c) { c = document.createElement('div'); c.id = 'notifications-toasts'; c.setAttribute('aria-live', 'polite'); document.body.appendChild(c); }
        return c;
    }

    function toast(item, ttl) {
        var el = document.createElement(item.url ? 'a' : 'div');
        el.className = 'notification-toast';
        if (item.url) el.href = item.url;
        el.innerHTML = '<span class="notification-toast-icon"><i class="fa-solid fa-bell"></i></span>' +
            '<span class="notification-toast-text"><span class="notification-toast-title"></span><span class="notification-toast-body"></span></span>' +
            '<button type="button" class="notification-toast-close" aria-label="close">&times;</button>';
        el.querySelector('.notification-toast-title').textContent = item.title || '';
        el.querySelector('.notification-toast-body').textContent = item.content || '';
        if (!item.title) el.querySelector('.notification-toast-title').remove();
        var dismiss = function () { if (el.classList.contains('is-leaving')) return; el.classList.add('is-leaving'); setTimeout(function () { el.remove(); }, 260); };
        el.querySelector('.notification-toast-close').addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); dismiss(); });
        el.addEventListener('click', function () { if (item.id) markRead(item.id); dismiss(); });
        toastContainer().appendChild(el);
        setTimeout(dismiss, ttl || 9000);
        return el;
    }

    var announcing = false;
    function announceNew() {
        var url = cfg('latest-url');
        if (!url || announcing) return;
        announcing = true;
        fetch(url + '?limit=6', { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (json) {
            announcing = false;
            if (!json) return;
            setUnread(json.unread || 0);
            var seen = seenIds();
            var fresh = (json.items || []).filter(function (n) { return !n.isRead && seen.indexOf(n.id) === -1; });
            // First visit from this browser: don't dump the whole backlog as
            // toasts, just remember it.
            var firstTime = seen.length === 0;
            if (!firstTime) fresh.slice(0, 3).forEach(function (n) { toast(n); });
            rememberSeen(seen.concat((json.items || []).map(function (n) { return n.id; })));
        }).catch(function () { announcing = false; });
    }

    function sync() {
        // A navigation replaced the page: whatever was open belongs to the
        // old one.
        closePanels();
        syncPushButtons();
        if (root()) announceNew();
        var badge = document.querySelector('[data-notifications-badge]');
        if (badge) setUnread(parseInt(badge.getAttribute('data-count') || badge.textContent, 10) || 0);
    }

    // A tab that sat in the background comes back with a stale badge; the
    // latest endpoint is cheap, and focus is a user-driven rate.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && root()) announceNew();
    });

    window.addEventListener('load', sync);
    if (document.readyState === 'complete') sync();

    window.__baseNotificationsBound = { sync: sync, enablePush: enablePush, disablePush: disablePush, markAllRead: markAllRead, toggleRead: toggleRead, deleteOne: deleteOne, deleteRead: deleteRead, toast: toast, announceNew: announceNew };
})();
