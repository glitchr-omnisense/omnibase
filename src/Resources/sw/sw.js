/*
 * Notification service worker (served by Base\Controller\Client\NotificationController
 * at /sw.js so its scope is the whole origin).
 *
 * Deliberately minimal: no caching, no offline - it exists to receive Web
 * Push messages and turn them into browser notifications, and to open the
 * right page when one is clicked. The payload is the JSON WebPushService
 * sends: {title, body, url, icon, tag}.
 */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: event.data ? event.data.text() : '' }; }

    var title = data.title || self.location.hostname;
    var options = {
        body: data.body || '',
        icon: data.icon || '/apple-touch-icon.png',
        badge: data.badge || undefined,
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: data.url || '/' }
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';
    var target = new URL(url, self.location.origin).href;

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
        for (var i = 0; i < windows.length; i++) {
            if (windows[i].url === target && 'focus' in windows[i]) return windows[i].focus();
        }
        // Reuse any open tab of the site rather than piling up windows.
        for (var j = 0; j < windows.length; j++) {
            if ('navigate' in windows[j] && 'focus' in windows[j]) return windows[j].navigate(target).then(function (w) { return w && w.focus(); });
        }
        return self.clients.openWindow(target);
    }));
});
