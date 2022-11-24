$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.money", function () {

        document.querySelectorAll("[data-currency-field]").forEach((function (e) {

            var currency = $("#"+$(e).data("currency-field"));
            var input    = $(e);

            var id       = $(input).attr("id");
            var btn      = $("#"+id+"-btn");
            var selected = $("#"+id+"-selected");
            var list     = $("#"+id+"-list").find("li.item");

            var amount       = $("#"+id).val();
            var scale        = $(e).data("scale-field") || 0;
            var target       = $("#"+$(e).data("target-field"));
            var baseExchange = 1;

            function str2num(amount, scale) {

                var decimal   = amount[amount.length - (scale+1)] || ".";
                if (!isNaN(parseInt(decimal))) decimal = ".";

                [a, b] = amount.split(decimal);

                var factor = 10**scale;
                return (factor*parseInt(a) + parseInt(b))/factor;
            }

            function num2str(amount, scale, decimal = ",") {

                var separator = (decimal == ".") ? "," : " ";
                [a,b] = amount.toString().split(".");

                a = a || "";
                b = b || "";

                var scale2 = b.length - scale;
                    scale2 = scale2 < 0 ? 0 : scale2;

                var factor = 10**scale2;
                if(b == "") b = 0;

                b = Math.round(parseInt(b)/factor);
                b = b.toString();

                while(b.length < scale)
                    b = b+"0";

                function addCommas(x) { return x.replace(/\B(?=(\d{3})+(?!\d))/g, separator); }
                return   addCommas(a)+decimal+addCommas(b);
            }

            var decimal   = amount[amount.length - (scale+1)] || ".";
            var num = str2num(amount, scale);
            input.val(num2str(num, scale, decimal));

            $(input).off("redo");
            $(input).on ("redo", function(e) { $(input).val(e.value); });
            $(input).off("undo");
            $(input).on ("undo", function(e) { $(input).val(e.value); });

            $(input).off("input");
            $(input).on("input", function() {

                var val = input.val().replace(/[^0-9]/g, '');
                var factor = 10**scale;

                input.val(num2str(parseInt(val)/factor, scale, decimal));
                num = input.val();
            });

            list.off("click");
            list.on("click", function() {

                var label    = $(this).data("label");
                var code     = $(this).data("code");
                var exchange = $(this).data("exchange");

                $(selected).html(this.innerHTML);
                $(btn).html(label);

                num = num*exchange/baseExchange;
                baseExchange = exchange;

                $(input).val(num2str(num, scale));
                if(currency) $(currency).val(code);
            });

        }))
    });

    $(document).trigger("load.form_type.money");
});