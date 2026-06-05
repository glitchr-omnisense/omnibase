import $ from 'jquery';
import './styles/easyadmin-async.scss';

// Turbo for the EasyAdmin /admin section.
//
// transparent.js's Settings.exceptions excludes /admin from SPA handling
// because EasyAdmin's heavy forms / modals / Bootstrap components don't
// survive transparent.js's full-#page innerHTML swap. Turbo Drive is a
// better fit for /admin:
//   - swaps <body> only, preserving controllers that hang off <html>
//   - intercepts <form> submissions natively (Turbo Forms)
//   - <turbo-frame> for partial updates of EA list/edit tables
//
// The layout.html.twig sets data-turbo="true" on <html>, but Turbo only
// activates if its package side-effects run. Importing here registers
// Turbo's listeners and exposes window.Turbo for any project code that
// needs to call into it (e.g. Turbo.visit, turbo:before-fetch-response).
import '@hotwired/turbo';

// ── Turbo scope guard — keep Turbo INSIDE /admin only ───────────────────
//
// Turbo is loaded on every EasyAdmin page via this bundle. Without a scope
// guard, it intercepts ALL same-origin clicks — including links from the
// admin to the public site (e.g., the "view article" link on an article
// edit page that targets /japon/articles/N). Turbo would then fetch the
// public page and try to swap <body> into the current document, but the
// public site is driven by transparent.js (not Turbo), so the swap leaves
// the new page in a half-initialized state.
//
// Two complementary guards covering the two ways a navigation can start:
//
//   1. `turbo:click` — when a user clicks an <a>. Per Turbo's docs,
//      cancelling this event lets the browser handle the click normally
//      (i.e. the <a>'s default navigation runs). No need to set
//      window.location ourselves — doing so would cause double navigation.
//
//   2. `turbo:before-visit` — for programmatic visits via Turbo.visit()
//      AND for the Turbo-following side of a form-submit redirect. When
//      there's no click default to fall through to, we explicitly call
//      window.location.assign() to do the navigation.
//
// Returning to /admin from the public site is unaffected: transparent.js
// hands off via a real navigation (it has `/admin` in its exceptions
// list), the page reloads, and Turbo re-initializes from fresh HTML.
function _isAdminUrl(url) {
    try {
        var u = new URL(url, document.baseURI);
        if (u.origin !== location.origin) return false; // off-site
        return u.pathname === '/admin' || u.pathname.indexOf('/admin/') === 0;
    } catch (e) {
        return false;
    }
}

document.addEventListener('turbo:click', function (event) {
    var url = event.detail && event.detail.url;
    if (!url || _isAdminUrl(url)) return;
    // Cancel Turbo's interception. The <a>'s native click navigation
    // takes over — DO NOT set location.href here (would double-nav).
    event.preventDefault();
});

document.addEventListener('turbo:before-visit', function (event) {
    var url = event.detail && event.detail.url;
    if (!url || _isAdminUrl(url)) return;
    // Programmatic visit OR redirect-followed visit. No native click to
    // fall through to, so we explicitly do the navigation.
    event.preventDefault();
    window.location.assign(url);
});

// ── Turbo → DOMContentLoaded bridge ─────────────────────────────────────
//
// Form-defer scripts (form-type-editor.js, dropzone, select2, etc.) all
// register their initializers on `window` DOMContentLoaded:
//
//     $(window).on("DOMContentLoaded.edjs", function() {
//         $("[data-edjs]").each(function() { edjs(...); });
//     });
//
// On the initial /admin page load this fires normally and Editor.js,
// Dropzone audio upload, Select2, etc. all get bound. But Turbo Drive
// SPA-navigates by swapping <body> content WITHOUT re-firing
// DOMContentLoaded — so on /admin/article → /admin/article/N/edit, the
// new form's <textarea data-edjs>, Dropzone <div>, and Select2 <select>
// elements are inserted into the DOM but their initializers never run.
// The result the user sees: Audio upload stuck on "Merci de patienter..",
// Content field empty (no Editor.js instance), Followers select empty.
//
// Bridge: when Turbo finishes a navigation, synthetically re-dispatch
// DOMContentLoaded so every existing form-defer initializer runs against
// the newly-inserted markup. Most form-defer scripts already use
// $(...).off("DOMContentLoaded.XYZ") before re-binding, so re-firing is
// idempotent: existing widgets get torn down and rebuilt, new ones get
// initialized. Editor.js specifically clears its holder's innerHTML
// inside edjs(...) before instantiating, so duplicate runs are safe.
document.addEventListener("turbo:load", function () {
    document.dispatchEvent(new Event("DOMContentLoaded"));
    window.dispatchEvent(new Event("DOMContentLoaded"));
});

var spinnerTimeout = setTimeout(function () { $(".content").addClass("spinner"); }, 1000);
$(window).on("load", function (e) {

    $(".content").addClass("spinner");
    $(".spinner").addClass("spinner loaded");
    clearTimeout(spinnerTimeout);
});

//
// Apply bootstrap form validation
window.addEventListener('load', function (event) {

    $("form :input").on("change", function () {
    // Reactivate button when a form is changed
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    $("form :input").on("input", function () {
    // Reactivate button when a form is changed
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    // Input event is not working sometimes for select2
    const observer = new MutationObserver(() => {
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    $("form select").each(function () {
        observer.observe(this, {subtree: true, childList: true});
    });
    
    // Look for <a data-ea-filter-scheme="..."> buttons
    const html = document.documentElement;

    // Helper to apply or remove filter
    function applyFilter(scheme) {
        switch (scheme) {
            case 'mono':
                html.style.filter = 'grayscale(1)';
                break;
            case 'sepia':
                html.style.filter = 'sepia(1)';
                break;
            case 'none':
            default:
                html.style.filter = '';
        }
        localStorage.setItem('ea-filter-scheme', scheme);
        updateEyeDropperIcons(scheme);
    }

    // Update <i class="fa-solid fa-droplet"> icon based on filter state
    function updateEyeDropperIcons(scheme) {
        $('i.fa-solid.fa-droplet, i.fa-solid.fa-droplet-slash').each(function () {
            if (scheme && scheme !== 'none') {
                $(this).removeClass('fa-droplet').addClass('fa-droplet-slash');
            } else {
                $(this).removeClass('fa-droplet-slash').addClass('fa-droplet');
            }
        });
    }

    // On page load, check localStorage first
    let stored = localStorage.getItem('ea-filter-scheme');
    if (stored) {
        applyFilter(stored);
    } else {
        updateEyeDropperIcons('none');
    }

    // Listen for filter scheme button clicks
    $(document).on('click', '[data-ea-filter-scheme]', function (e) {
        e.preventDefault();
        const scheme = $(this).attr('data-ea-filter-scheme');
        let current = localStorage.getItem('ea-filter-scheme');
        if (current === scheme) {
            // Toggle off if already active
            applyFilter('none');
        } else {
            applyFilter(scheme);
        }
    });
});