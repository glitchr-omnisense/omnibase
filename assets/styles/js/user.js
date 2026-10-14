function getLocale() {
    return navigator.languages && navigator.languages.length ? navigator.languages[0] : navigator.language;
}

// The page's scripts have all run: the categories a site lets its visitors
// answer for (omnibase/consent hands them to Cookie) are known.
var settled = false;

//
// The time zone has a say of its own where the site asks for one: a LOCATION
// category the visitor answers for (omnibase/consent's default). It is then
// kept apart, in LOCATION/TIMEZONE, written while that category is granted and
// erased when it is refused. Without one it goes with the rest in USER/INFO,
// as it always did.
//
function zoneGroup() {
    var category = typeof Cookie.category === 'function' ? Cookie.category('LOCATION') : null;
    return category && !category.onDemand ? 'LOCATION' : 'USER';
}

function getUser()
{
    var opts = Intl.DateTimeFormat(getLocale()).resolvedOptions();
    var user = {
        // No `time` here: Cookie.set(..., true) reloads the page whenever the
        // stored value differs, and a fresh Date() differs on every load - so
        // every visitor's first page was rendered, thrown away and fetched
        // again, on every page. Nothing on the server reads it (the timezone
        // is what tells the server the clock), and the rest of the object
        // only changes when the browser really does, which is when a reload
        // is what one wants.
        calendar:opts.calendar,
        locale:opts.locale,
        darkMode: window.matchMedia('(prefers-color-scheme: dark)').matches,
        numberingSystem:opts.numberingSystem,
        browser(){return navigator.userAgent},
        viewport(){return [
            Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0),
            Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0)]
        }
    };
    // Before the categories are known the zone is left out: the write that
    // follows, once they are, puts it where it belongs.
    if (settled && zoneGroup() === 'USER') user.timezone = opts.timeZone;

    return user;
}

//
// Save user information
//
// Through Cookie.set(), which writes nothing under a category the visitor
// refused: USER for the browser's language, calendar and theme, LOCATION (or
// USER, see zoneGroup()) for the time zone.
//
// Without a reload. The last argument of Cookie.set() used to be `true`
// (reloadIfNotSet): a visit without this cookie - a first visit, a private
// window, an expired cookie - rendered the page, wrote the cookie and reloaded
// at once, so the server could read the timezone and locale on the second
// render. That loaded every such page twice, and Safari reported each preload
// of the discarded first render as "not used". The server falls back without
// the cookie (UTC, the URL or LANG locale); the next page already has it.
function saveUser() {
    Cookie.set("USER", "INFO", getUser(), 30*24*3600);
    if (settled && zoneGroup() === 'LOCATION') {
        Cookie.set("LOCATION", "TIMEZONE", Intl.DateTimeFormat(getLocale()).resolvedOptions().timeZone, 30*24*3600);
    }
}

Cookie.ready();
saveUser();

window.addEventListener('DOMContentLoaded', function(event) {

    settled = true;
    saveUser();

    window.addEventListener('resize', saveUser);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', saveUser);
    // An answer just given: what it grants is written now, not at the next page.
    window.addEventListener('cookie:consent', saveUser);

    Clipboard.ready();
});
