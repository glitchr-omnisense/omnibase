function getLocale() {
    return navigator.languages && navigator.languages.length ? navigator.languages[0] : navigator.language;
}

function getUser()
{
    var opts = Intl.DateTimeFormat(getLocale()).resolvedOptions();
    return {
        time:new Date(),
        timezone:opts.timeZone,
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
}

//
// Save user information
//
// Without a reload. The last argument of Cookie.set() used to be `true`
// (reloadIfNotSet): a visit without this cookie - a first visit, a private
// window, an expired cookie - rendered the page, wrote the cookie and reloaded
// at once, so the server could read the timezone and locale on the second
// render. That loaded every such page twice, and Safari reported each preload
// of the discarded first render as "not used". The server falls back without
// the cookie (UTC, the URL or LANG locale); the next page already has it.
Cookie.ready();
Cookie.set("USER", "INFO", getUser(), 30*24*3600);

window.addEventListener('DOMContentLoaded', function(event) {

    Cookie.set("USER", "INFO", getUser(), 30*24*3600);

    window.addEventListener('resize', () => Cookie.set("USER", "INFO", getUser(), 30*24*3600));
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => Cookie.set("USER", "INFO", getUser(), 30*24*3600));

    Clipboard.ready();
});