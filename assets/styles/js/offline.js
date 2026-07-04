/**
 * Offline mode — YouTube-style full-page takeover.
 *
 * When the browser loses connectivity (`offline` event, a failed network
 * request, or `navigator.onLine` false at load), the current page is covered
 * by a full-viewport "You're offline" screen with a Retry button. It hides
 * automatically as soon as connectivity returns (`online` event).
 *
 * Strings can be localized by the application via attributes on <html>:
 *   data-offline-title, data-offline-message, data-offline-retry
 *
 * Programmatic access: window.OfflineMode.show() / .hide() / .isOffline()
 */
(function () {

    var overlay = null;

    function attr(name, fallback) {
        return document.documentElement.getAttribute(name) || fallback;
    }

    function build() {

        if (overlay) return overlay;

        overlay = document.createElement("div");
        overlay.id = "offline-page";
        overlay.setAttribute("role", "alert");
        overlay.setAttribute("aria-live", "assertive");

        var inner = document.createElement("div");
        inner.className = "offline-inner";

        var icon = document.createElement("i");
        icon.className = "bi bi-wifi-off offline-icon";
        icon.setAttribute("aria-hidden", "true");

        var title = document.createElement("h1");
        title.className = "offline-title";
        title.textContent = attr("data-offline-title", "You are offline");

        var message = document.createElement("p");
        message.className = "offline-message";
        message.textContent = attr("data-offline-message", "Check your internet connection and try again.");

        var retry = document.createElement("button");
        retry.type = "button";
        retry.className = "offline-retry";
        retry.textContent = attr("data-offline-retry", "Retry");
        retry.addEventListener("click", function () {

            if (navigator.onLine) {
                hide();
                location.reload();
            } else {
                // Still offline: give visual feedback instead of reloading
                // into the browser's own error page.
                retry.classList.remove("offline-shake");
                void retry.offsetWidth; // restart the animation
                retry.classList.add("offline-shake");
            }
        });

        inner.appendChild(icon);
        inner.appendChild(title);
        inner.appendChild(message);
        inner.appendChild(retry);
        overlay.appendChild(inner);

        return overlay;
    }

    function show() {

        build();
        if (!overlay.parentNode) document.body.appendChild(overlay);

        var retry = overlay.querySelector(".offline-retry");
        if (retry) retry.focus({ preventScroll: true });
    }

    function hide() {
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
    }

    window.addEventListener("offline", show);
    window.addEventListener("online", hide);

    // A request that dies with status 0 while the interface reports offline is
    // a real connectivity failure (status 0 alone can also be an abort or a
    // CORS issue, so both conditions are required). Catches SPA navigations
    // (@glitchr/transparent) and infinite-scroll loads (@glitchr/ajaxer)
    // failing before the `offline` event has fired.
    if (window.jQuery) {
        jQuery(document).ajaxError(function (event, xhr, settings, error) {
            if (error === "abort") return;
            if (xhr && xhr.status === 0 && !navigator.onLine) show();
        });
    }

    // Page restored/loaded while already offline
    if (!navigator.onLine) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", function () { if (!navigator.onLine) show(); });
        } else {
            show();
        }
    }

    window.OfflineMode = {
        show: show,
        hide: hide,
        isOffline: function () { return !navigator.onLine; }
    };
})();
