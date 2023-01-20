import '@glitchr/datetime-picker'

$(window).off("DOMContentLoaded.form_type.datetimepicker");
$(window).on("DOMContentLoaded.form_type.datetimepicker", function () {

    $(window).on("load.form_type.datetimepicker");
    $(window).on("load.form_type.datetimepicker", function () {

        document.querySelectorAll("[data-datetimepicker-field]").forEach((function (el) {

            var id             = el.getAttribute("data-datetimepicker-field");
            var datetimepicker = $(el).data('datetimepicker-options');

            var parent = $('#'+id).parent();
            $(parent).css('position', 'relative');

            var isVisible = false;
            $('#'+id+"-btn").off("click");
            $('#'+id+"-btn").on("click", function() {
                $('#'+id).datetimepicker(isVisible ? "hide" : "show");
                isVisible = !isVisible;
            });

            $('#'+id).datetimepicker(datetimepicker);
        }));
    });

    $(window).trigger("load.form_type.datetimepicker");
});

$(window).trigger("DOMContentLoaded.form_type.datetimepicker");