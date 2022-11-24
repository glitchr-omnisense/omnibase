
function getUser()
{
    var opts = Intl.DateTimeFormat().resolvedOptions();

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
CookieConsent.ready();
CookieConsent.setCookie("user", "necessary", getUser(), 30*24*3600, true);

window.addEventListener('DOMContentLoaded', function(event) {

    CookieConsent.setCookie("user", "necessary", getUser(), 30*24*3600, true);

    window.addEventListener('resize', () => CookieConsent.setCookie("user", "necessary", getUser(), 30*24*3600));
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => CookieConsent.setCookie("user", "necessary", getUser(), 30*24*3600));

    Clipboard.ready();
});