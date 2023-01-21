import '@glitchr/datetime-picker'

window.addEventListener("load.form_type", function () {

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