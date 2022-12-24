
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

$(window).on("load", function ()
{
    CookieConsent.onConfirm(function()
    {
        $("#gdpr-cookie").removeClass("fa-cookie").addClass("fa-cookie-bite");
        $("#gdpr-icon"  ).removeClass("far fa-circle").addClass("far fa-check-circle").removeClass("fas fa-exclamation-circle");
        $("#gdpr-icon"  ).removeClass("warning").addClass("success");
        $("#gdpr-text"  ).removeClass("open");
    });

    CookieConsent.onDeny(function()
    {
        $("#gdpr-cookie").removeClass("fa-cookie-bite").addClass("fa-cookie");
        $("#gdpr-icon"  ).removeClass("far fa-check-circle").addClass("fas fa-exclamation-circle").removeClass("far fa-circle");
        $("#gdpr-icon"  ).removeClass("success").addClass("warning");
        $("#gdpr-text"  ).addClass("open");
    });

    CookieConsent.onCheck(function()
    {
        $("#gdpr-cookie").removeClass("fa-cookie-bite").addClass("fa-cookie");
        $("#gdpr-icon"  ).removeClass("far fa-check-circle").removeClass("fas fa-exclamation-circle").addClass("far fa-circle");
        $("#gdpr-icon"  ).removeClass("warning").removeClass("success");
        $("#gdpr-text"  ).removeClass("open");
    });

    CookieConsent.ready();
    $("#gdpr").on("swalk:ask",     () => CookieConsent.refresh(null));
    $("#gdpr").on("swalk:close",   () => CookieConsent.refresh());
    $("#gdpr").on("swalk:confirm", () => CookieConsent.change(true));
    $("#gdpr").on("swalk:deny"   , () => CookieConsent.change(false));
});