
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
Cookie.ready();
Cookie.set("USER", "INFO", getUser(), 30*24*3600, true);

window.addEventListener('DOMContentLoaded', function(event) {

    Cookie.set("USER", "INFO", getUser(), 30*24*3600, true);

    window.addEventListener('resize', () => Cookie.set("USER", "INFO", getUser(), 30*24*3600));
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => Cookie.set("USER", "INFO", getUser(), 30*24*3600));

    Clipboard.ready();
});

$(window).on("load", function ()
{
    Cookie.onConfirm(function()
    {
        $("#gdpr-cookie").removeClass("fa-cookie").addClass("fa-cookie-bite");
        $("#gdpr-icon"  ).removeClass("far fa-circle").addClass("far fa-check-circle").removeClass("fas fa-exclamation-circle");
        $("#gdpr-icon"  ).removeClass("warning").addClass("success");
        $("#gdpr-text"  ).removeClass("open");
    });

    Cookie.onDeny(function()
    {
        $("#gdpr-cookie").removeClass("fa-cookie-bite").addClass("fa-cookie");
        $("#gdpr-icon"  ).removeClass("far fa-check-circle").addClass("fas fa-exclamation-circle").removeClass("far fa-circle");
        $("#gdpr-icon"  ).removeClass("success").addClass("warning");
        $("#gdpr-text"  ).addClass("open");
    });

    Cookie.onCheck(function()
    {
        $("#gdpr-cookie").removeClass("fa-cookie-bite").addClass("fa-cookie");
        $("#gdpr-icon"  ).removeClass("far fa-check-circle").removeClass("fas fa-exclamation-circle").addClass("far fa-circle");
        $("#gdpr-icon"  ).removeClass("warning").removeClass("success");
        $("#gdpr-text"  ).removeClass("open");
    });

    Cookie.ready();
    $("#gdpr").on("swalk:ask",     () => Cookie.refresh(null));
    $("#gdpr").on("swalk:close",   () => Cookie.refresh());
    $("#gdpr").on("swalk:confirm", () => Cookie.setConsent(true));
    $("#gdpr").on("swalk:deny"   , () => Cookie.setConsent(false));
});