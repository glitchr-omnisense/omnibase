$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.number", function () {

        document.querySelectorAll("[data-number-field]").forEach((function (e) {

            var id = $(e).data("number-field");
            var input = $("#"+id);

            var min = $(e).data("number-min");
            var max = $(e).data("number-max");
            var stepUp = $(e).data("number-up");
            var stepDown = $(e).data("number-down");

            var keyUp = $(e).data("number-keyup");
            var keyDown = $(e).data("number-keydown");

            var throttleDown =  $(e).data("number-down-throttle");
            var throttleUp   =  $(e).data("number-up-throttle");

            var btnDown = $("#"+id+"-down");
            var btnUp = $("#"+id+"-up");

            var invervalBtnUp, invervalBtnDown;
            var intervalKeyUp, intervalKeyDown;

            // Do not necessarily display by default..
            // var numberValue = $(input).val() || 0;
            // if(numberValue === 0) $(input).val(0);

            var onClickUp = function() {

                var val = parseFloat($(input).val());
                if (isNaN(val))
                    val = 0;
                if (val < parseFloat(max) || max === undefined)
                    val = val + Math.abs(parseFloat(stepUp ?? 1));
                if (val > parseFloat(max) &&  max !== undefined)
                    val = parseFloat(max);

                $(input).val(val);
                $(input).trigger("input");
            }

            var onClickDown = function() {

                var val = parseFloat($(input).val());
                if (isNaN(val))
                    val = 0;

                if (val > parseFloat(min) || min === undefined)
                    val = val - Math.abs(parseFloat(stepDown ?? 1));

                if (val < parseFloat(min) &&  min !== undefined)
                    val = parseFloat(min);

                $(input).val(val);
                $(input).trigger("input");
            };

            // $(window).off("keydown.number."+id);
            // $(window).on("keydown.number."+id, function (e) {

            //     var code = (e.keyCode ? e.keyCode : e.which);
            //     if (code == 38 && keyUp && $("#"+id+':focus').length) {

            //         onClickUp();
            //         if(throttleUp)
            //             intervalKeyUp = setInterval(onClickUp, throttleUp);

            //     } else if (code == 38 && keyUp && $("#"+id+':focus').length) {

            //         onClickDown();
            //         if(throttleDown)
            //             intervalKeyDown = setInterval(onClickDown, throttleDown);
            //     }

            // }).on('keyup.number.'+id, function() {

            //     var code = (e.keyCode ? e.keyCode : e.which);
            //     if (code == 38 && keyUp && $("#"+id+':focus').length && intervalKeyUp)
            //         clearInterval(intervalKeyUp);
            //     if (code == 39 && keyDown && $("#"+id+':focus').length && intervalKeyDown)
            //         clearInterval(intervalKeyDown);
            // });

            $(btnUp).off("mousedown.number mouseup.number mouseleave.number touchstart.number touchend.number touchcancel.number");
            $(btnUp).on("mousedown.number touchstart.number", function() {

                onClickUp();
                if(throttleUp)
                    invervalBtnUp = setInterval(onClickUp, throttleUp);

            }).on('mouseup.number mouseleave.number touchend.number touchcancel.number', function() {
                 if(invervalBtnUp) clearInterval(invervalBtnUp);
            });

            $(btnDown).off("mousedown.number mouseup.number mouseleave.number touchstart.number touchend.number touchcancel.number");
            $(btnDown).on("mousedown.number touchstart.number", function() {

                onClickDown();
                if(throttleDown)
                    invervalBtnDown = setInterval(onClickDown, throttleDown);

            }).on('mouseup.number mouseleave.number touchend.number touchcancel.number', function() {
                if(invervalBtnDown) clearInterval(invervalBtnDown);
            });

            $(input).off("input.number");
            $(input).on("input.number", function() {

                if ($(input).val()) numberValue = $(input).val();
                if ($(input).val() == "") setUnlimitedState(input);
            });
        }));
    });

    $(document).trigger("load.form_type.number");
});