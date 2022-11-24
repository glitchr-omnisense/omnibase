$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.stock", function () {

        document.querySelectorAll("[data-stock-field]").forEach((function (e) {

            var id = $(e).data("stock-field");
            var unlimitedStr = $(e).data("stock-unlimited");

            var input = $("#"+id);

            var btn   = $("#"+id+"-btn");
            var btnDown = $("#"+id+"-down");
            var btnUp = $("#"+id+"-up");

            var icon  = $(btn).find("i");

            $(input).prop("placeholder", unlimitedStr);
            function isUnlimited(input) { return $(input).prop("disabled") && $(input).val() == ""; }
            function setLimitedState(input, stockValue)
            {
                $(input).val(stockValue);

                $(btnUp  ).prop("disabled", false);
                $(btnDown).prop("disabled", false);
                $(input  ).prop("disabled", false);
                $(icon).addClass("fa-box-open").removeClass("fa-infinity");
                return $(input).val();
            }

            function setUnlimitedState(input)
            {
                var stockValue = $(input).val() || null;
                $(input).val(null);

                $(btnUp  ).prop("disabled", true);
                $(btnDown).prop("disabled", true);
                $(input  ).prop("disabled", true);
                $(icon).removeClass("fa-box-open").addClass("fa-infinity");
                return stockValue;
            }

            var stockValue = $(input).val() || null;
            if ($(input).val() == "") stockValue = setUnlimitedState(input);
            else stockValue = setLimitedState(input, stockValue);

            $(btn).off("click");
            $(btn).on("click", function() {

                if (isUnlimited(input)) stockValue = setLimitedState(input, stockValue);
                else stockValue = setUnlimitedState(input);

            });

            $(btnUp).off("click");
            $(btnUp).on("click", function()
            {
                var stepUp = parseInt($(input).attr("data-stock-up")) ?? 1;
                if (stepUp == 0) stepUp = 1;

                var val = parseInt($(input).val());
                if (isNaN(val)) val = 0;
                if (val < parseInt($(input).attr("data-stock-max")) || !$(input).attr("data-stock-max"))
                    val = val + Math.abs(stepUp);
                if (val > parseInt($(input).attr("data-stock-max")) && $(input).attr("data-stock-max"))
                    val = parseInt($(input).attr("data-stock-max"));

                $(input).val(val);
            });

            $(btnDown).off("click");
            $(btnDown).on("click", function()
            {
                var stepDown = parseInt($(input).attr("data-stock-down")) ?? 1;
                if (stepDown == 0) stepDown = 1;

                var val = parseInt($(input).val());
                if (isNaN(val)) val = 0;
                if (val > parseInt($(input).attr("data-stock-min")) || !$(input).attr("data-stock-min"))
                    val = val - Math.abs(stepDown);
                if (val < parseInt($(input).attr("data-stock-min")) && $(input).attr("data-stock-min"))
                    val = parseInt($(input).attr("data-stock-min"));

                $(input).val(val);
            });

            $(input).off("change");
            $(input).on("change", function() {

                if ($(input).val()) stockValue = $(input).val();
                if ($(input).val() == "") setUnlimitedState(input);
            });
        }))
    });

    $(document).trigger("load.form_type.stock");
});