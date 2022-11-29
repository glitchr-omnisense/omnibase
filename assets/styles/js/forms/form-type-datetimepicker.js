import '@glitchr/datetime-picker'

$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.datetimepicker", function () {

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
                
            console.log(datetimepicker);
            $('#'+id).datetimepicker(datetimepicker);
        }));
    });

    $(document).trigger("load.form_type.datetimepicker");
});
