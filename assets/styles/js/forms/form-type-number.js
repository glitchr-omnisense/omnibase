window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-number-field]").forEach((function (e) {

        var id = $(e).data("number-field");
        var input = $("#"+id);

        var min = $(e).data("number-min");
        var max = $(e).data("number-max");
        var suffix = $(e).data("number-suffix");
        var prefix = $(e).data("number-prefix");
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

            var number = $(input).val().replaceAll(/[^\d.,]+/ig, "");
            var val = parseFloat(number);

            if (isNaN(val))
                val = 0;

            if (val < parseFloat(max) || isNaN(parseFloat(max)))
                val = val + Math.abs(parseFloat(stepUp ?? 1));

            if (val > parseFloat(max) && !isNaN(parseFloat(max)))
                val = parseFloat(max);

            $(input).val(val);
            $(input).trigger("input");
        }

        var onClickDown = function() {

            var number = $(input).val().replaceAll(/[^\d.,]+/ig, "");
            var val = parseFloat(number);

            if (isNaN(val))
                val = 0;

            if (val > parseFloat(min) || isNaN(parseFloat(min)))
                val = val - Math.abs(parseFloat(stepDown ?? 1));

            if (val < parseFloat(min) && !isNaN(parseFloat(min)))
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

        var format = function() {

            var number = $(this).val();
                number = number.replaceAll(/[^\d.,]+/ig, "").replaceAll(/^0/ig, "");
            if(!number) number = 0;

            $(this).val(prefix+number+suffix);
        };

        $(input).off("input.number");
        $(input).on("input.number", function () {

            var number = $(input).val();
                number = number.replaceAll(/[^\d.,]+/ig, "").replaceAll(/^0/ig, "");

            if(!number) number = 0;

            $(input).val(prefix+number+suffix);
        });

        var number = $(input).val();
            number = number.replaceAll(/[^\d.,]+/ig, "").replaceAll(/^0/ig, "");
        if(!number) number = 0;

        $(input).val(prefix+number+suffix);
    }));
});
