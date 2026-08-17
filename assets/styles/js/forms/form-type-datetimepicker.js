import 'flatpickr/dist/flatpickr.js';
import 'flatpickr/dist/l10n';

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-datetimepicker-field]").forEach((function (el) {

        // "load.form_type" is dispatched globally on every lazy load (a
        // collection's "load more", form-type-array's own re-dispatch, ...),
        // not just the real page load - and this querySelectorAll is
        // unscoped, so it re-matches every ALREADY-initialized datetime
        // field on the page too. Without this guard, each re-fire stacked a
        // second flatpickr instance (and its own calendar popup + outside-
        // click handler) on the same input; closing/saving the form then
        // raced both instances' cleanup over removing the same calendar
        // node, one of them finding it already gone - reported live as
        // "issue deleting flatpickr-calendar...". flatpickr sets this back
        // on the element itself once initialized, so it's a reliable,
        // no-extra-state way to make this idempotent.
        if (el._flatpickr) return;

        var id             = el.getAttribute("data-datetimepicker-field");
        var datetimepicker = $(el).data('datetimepicker-options');

        flatpickr("#"+id, datetimepicker);
        $("#"+id).removeAttr("readonly");
    }));
});