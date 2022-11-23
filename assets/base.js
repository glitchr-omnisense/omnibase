
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

//
// Apply bootstrap form validation
// Lightbox configuration and countdown function
window.addEventListener('onbeforeunload', function(event) {

    $('#flash-messages').flashNotification('reset');
});

window.addEventListener('load', function(event) {

    $.fn.flashNotification = function(method) {

        var methods = {
            init: function(options) {


                methods.settings = $.extend({}, $.fn.flashNotification.defaults, options);
                methods.settings["container"] = $(this).length ? $(this)[0] : undefined;

                methods.display(".alert");

                methods.listenIncomingMessages();
            },

            reset: function(options) {
                $(document).unbind('ajaxComplete');
            },

            /**
             * Listen to AJAX responses and display messages if they contain some
             */
            listenIncomingMessages: function() {

                $(document).ajaxComplete(function(event, xhr, settings) {

                    if(!xhr || xhr.getResponseHeader("Content-Type") != "application/json")
                        return;

                    var data = $.parseJSON(xhr.responseText);
                    if (data.flashbag) {
                        var flashbag = data.flashbag;

                        var i;
                        if (flashbag.error) {
                            for (i = 0; i < flashbag.error.length; i++) {
                                methods.addError(flashbag.error[i]);
                            }
                        }

                        if (flashbag.success) {
                            for (i = 0; i < flashbag.success.length; i++) {
                                methods.addSuccess(flashbag.success[i]);
                            }
                        }

                        if (flashbag.warning) {
                            for (i = 0; i < flashbag.warning.length; i++) {
                                methods.addWarning(flashbag.warning[i]);
                            }
                        }

                        if (flashbag.info) {
                            for (i = 0; i < flashbag.info.length; i++) {
                                methods.addInfo(flashbag.info[i]);
                            }
                        }

                        methods.display(".alert");
                    }
                });
            },

            addSuccess: function(message) {
                var flashMessageElt = methods.getBasicFlash(message).addClass('alert-success');

                methods.addToList(flashMessageElt);
            },

            addError: function(message) {
                var flashMessageElt = methods.getBasicFlash(message).addClass('alert-error');

                methods.addToList(flashMessageElt);
            },

            addWarning: function(message) {
                var flashMessageElt = methods.getBasicFlash(message).addClass('alert-warning');

                methods.addToList(flashMessageElt);
            },

            addInfo: function(message) {
                var flashMessageElt = methods.getBasicFlash(message).addClass('alert-info');

                methods.addToList(flashMessageElt);
            },

            getBasicFlash: function(message) {
                var flashMessageElt = $('<div></div>')
                    .hide()
                    .addClass('alert alert-dismissible fade show')
                    .append($('<span class="message"></span>').html(message))
                    .append(methods.getCloseButton())
                ;

                return flashMessageElt;
            },

            getCloseButton: function()
            {
                var closeButtonElt = $('<button></button>')
                    .addClass('btn-close')
                    .attr('aria-label', 'Close')
                    .attr('onclick', "this.closest('.alert').remove()");

                return closeButtonElt;
            },

            addToList: function(flashMessageElt) {

                var message  = flashMessageElt.find(".message").text().trim();
                var messages = $('#flash-messages').find(".message").map(function(){ return $.trim($(this).text()); }).toArray();

                var index = messages.indexOf(message);
                while( index != -1) {

                    $('#flash-messages').find(".message").parent()[index].remove();

                    messages = $('#flash-messages').find(".message").map(function(){ return $.trim($(this).text()); }).toArray();
                    index = messages.indexOf(message);
                }

                flashMessageElt.appendTo(methods.settings.container);

                if(methods.settings.scrollUp) window.scrollTo(0, 0);
            },

            display: function(flashMessageElt) {

                setTimeout(
                    function() {

                        $(flashMessageElt).show(methods.settings.animation ? 'slow' : 0);
                        if(methods.settings.autoHide)
                            $(flashMessageElt).delay(methods.settings.hideDelay).hide(methods.settings.animation ? 'fast' : 0, function() { $(this).remove(); } );

                    },
                    500
                );
            }
        };

        // Method calling logic
        if (methods[method]) {
            return methods[ method ].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || ! method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error('Method ' +  method + ' does not exist on jQuery.flashNotification');
        }
    };

    $.fn.flashNotification.defaults = {
        'hideDelay'         : 9500,
        'autoHide'          : true,
        'animate'           : true,
        'scrollUp'          : true
    };

    $('#flash-messages').flashNotification('init');
    lightbox.option({'wrapAround': true});

    $("form.needs-validation input").on("invalid", (e) => e.preventDefault() );
    $("[type=submit]").click(function() {

        const style = getComputedStyle(document.body);

        var form = $(".has-error").closest("form.needs-validation");
        if(!form.length) form = $(this).closest("form.needs-validation");

        if (!this.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
        }

        var el = $(form).find(":invalid, .has-error");
        if (el.length) {

            return $([document.documentElement, document.body]).animate(
                {scrollTop: $(el[0]).offset().top - parseInt(style["scroll-padding-top"])},
                function() {
                    form.addClass('was-validated');
                }
            );
        }
    });

    function countdown(el, timestamp, reload, message = "")
    {
        // Set the date we're counting down to
        var timestamp = new Date(timestamp).getTime();

        // Update the count down every 1 second
        var x = setInterval(function() {

            // Get today's date and time
            var now = Math.floor(new Date().getTime()/1000);

            // Find the distance between now and the count down date
            var  countdown = timestamp - now;
            var _countdown = countdown;

            // Time calculations for days, hours, minutes and seconds
            seconds   = _countdown % 60;
            _countdown = Math.floor(_countdown/60);
            minutes   = _countdown % 60;
            _countdown = Math.floor(_countdown/60);
            hours     = _countdown % 24;
            _countdown = Math.floor(_countdown/24);
            days      = _countdown % 30;
            _countdown = Math.floor(_countdown/30);
            months    = _countdown % 12;
            years     = Math.floor(_countdown/12);

            var elYears       = $(el).find(".countdown-years");
            var oldYears = elYears.html();
                elYears.html(years).removeClass("blink").addClass(oldYears != years ? "blink" : "");
            var elMonths      = $(el).find(".countdown-months");
            var oldMonths = elMonths.html();
                elMonths.html(months).removeClass("blink").addClass(oldMonths != months ? "blink" : "");
            var elDays        = $(el).find(".countdown-days");
            var oldDays = elDays.html();
                elDays.html(days).removeClass("blink").addClass(oldDays != days ? "blink" : "");
            var elHours       = $(el).find(".countdown-hours");
            var oldHours = elHours.html();
                elHours.html(hours).removeClass("blink").addClass(oldHours != hours ? "blink" : "");
            var elMinutes     = $(el).find(".countdown-minutes");
            var oldMinutes = elMinutes.html();
                elMinutes.html(minutes).removeClass("blink").addClass(oldMinutes != minutes ? "blink" : "");
            var elSeconds     = $(el).find(".countdown-seconds");
            var oldSeconds = elSeconds.html();
                elSeconds.html(seconds).removeClass("blink").addClass(oldSeconds != seconds ? "blink" : "");

            var elYearsUnit   = $(elYears).parent().find(".countdown-unit");
            var oldYearsUnit = elYearsUnit.html();
                elYearsUnit.text(years > 1 ? elYearsUnit.data("plural") : elYearsUnit.data("singular"));
                elYearsUnit.removeClass("blink").addClass(oldYearsUnit != elYearsUnit.html() ? "blink" : "");
            var elMonthsUnit  = $(elMonths).parent().find(".countdown-unit");
            var oldMonthsUnit = elMonthsUnit.html();
                elMonthsUnit.text(months > 1 ? elMonthsUnit.data("plural") : elMonthsUnit.data("singular"));
                elMonthsUnit.removeClass("blink").addClass(oldMonthsUnit != elMonthsUnit.html() ? "blink" : "");
            var elDaysUnit    = $(elDays).parent().find(".countdown-unit");
            var oldDaysUnit = elDaysUnit.html();
                elDaysUnit.text(days > 1 ? elDaysUnit.data("plural") : elDaysUnit.data("singular"));
                elDaysUnit.removeClass("blink").addClass(oldDaysUnit != elDaysUnit.html() ? "blink" : "");
            var elHoursUnit   = $(elHours).parent().find(".countdown-unit");
            var oldHoursUnit = elHoursUnit.html();
                elHoursUnit.text(hours > 1 ? elHoursUnit.data("plural") : elHoursUnit.data("singular"));
                elHoursUnit.removeClass("blink").addClass(oldHoursUnit != elHoursUnit.html() ? "blink" : "");
            var elMinutesUnit = $(elMinutes).parent().find(".countdown-unit");
            var oldMinutesUnit = elMinutesUnit.html();
                elMinutesUnit.text(minutes > 1 ? elMinutesUnit.data("plural") : elMinutesUnit.data("singular"));
                elMinutesUnit.removeClass("blink").addClass(oldMinutesUnit != elMinutesUnit.html() ? "blink" : "");
            var elSecondsUnit = $(elSeconds).parent().find(".countdown-unit");
            var oldSecondsUnit = elSecondsUnit.html();
                elSecondsUnit.text(seconds > 1 ? elSecondsUnit.data("plural") : elSecondsUnit.data("singular"));
                elSecondsUnit.removeClass("blink").addClass(oldSecondsUnit != elSecondsUnit.html() ? "blink" : "");

            if(years < 1) {

                var grYears   = $(elYears).closest(".countdown-group");
                grYears.html("");
                if(months < 1) {

                    var grMonths  = $(elMonths).closest(".countdown-group");
                        grMonths.html("");

                    if(days < 1) {

                        var grDays    = $(elDays).closest(".countdown-group");
                            grDays.html("");

                        if(hours < 1) {

                            var grHours   = $(elHours).closest(".countdown-group");
                                grHours.html("");

                            if(minutes < 1) {

                                var grMinutes = $(elMinutes).closest(".countdown-group");
                                    grMinutes.html("");

                                if(seconds < 1) {

                                    var grSeconds = $(elSeconds).closest(".countdown-group");
                                        grSeconds.html("");
                                }
                            }
                        }
                    }
                }
            }

            // If the count down is finished, write some text
            if (countdown < 1 && _countdown > 0) {

                clearInterval(x);
                setTimeout(function() {

                    if(reload) location.reload();

                }, 1000);
            }

            $(el).removeClass("invisible");

        }, 1000);
    }

    const countdowns = document.querySelectorAll('[id^="countdown"]');
    countdowns.forEach(e => {
        countdown(e, $(e).data("timestamp"), true);
    });
});
