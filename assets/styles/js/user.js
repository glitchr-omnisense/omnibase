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
Cookie.ready();
Cookie.set("USER", "INFO", getUser(), 30*24*3600, true);

window.addEventListener('DOMContentLoaded', function(event) {

    Cookie.set("USER", "INFO", getUser(), 30*24*3600, true);

    window.addEventListener('resize', () => Cookie.set("USER", "INFO", getUser(), 30*24*3600));
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => Cookie.set("USER", "INFO", getUser(), 30*24*3600));

    Clipboard.ready();
});