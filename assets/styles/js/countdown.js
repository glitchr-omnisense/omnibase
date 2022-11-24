window.addEventListener('load', function(event) {

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