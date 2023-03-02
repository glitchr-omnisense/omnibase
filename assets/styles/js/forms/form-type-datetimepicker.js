import 'flatpickr/dist/flatpickr.js';
import 'flatpickr/dist/l10n';

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-datetimepicker-field]").forEach((function (el) {

        var id             = el.getAttribute("data-datetimepicker-field");
        var datetimepicker = $(el).data('datetimepicker-options');

        flatpickr("#"+id, datetimepicker);
    }));
});