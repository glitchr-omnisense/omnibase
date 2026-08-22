import 'flatpickr/dist/flatpickr.js';
import 'flatpickr/dist/l10n';

// Every flatpickr instance created here, so orphans can be destroyed on the
// next page transition - see __sweepOrphans__ below.
var __pickers = [];

/**
 * Destroys pickers whose input is no longer in the document.
 *
 * flatpickr appends its .flatpickr-calendar to document.BODY, deliberately -
 * it has to escape any overflow:hidden/transformed ancestor to position the
 * popup. But transparentJS's SPA navigation only swaps #page, so that calendar
 * node is outside everything it replaces and simply survives into the next
 * page, now detached from an input that no longer exists.
 *
 * On its own that would be an invisible leak (the popup is closed and absolutely
 * positioned). What makes it a visible bug is the head merge: navigating to a
 * page with no datetime field correctly drops form-defer.datetime's stylesheet,
 * and the orphan loses `position:absolute` along with everything else. It
 * reflows as a static, full-width block - measured live at 1905x3981px - so the
 * raw calendar markup (weekday letters run together, every day number inline,
 * the month arrows' SVGs at their intrinsic size) dumps itself into the page.
 *
 * Runs before re-initialising, and on plain 'load' too, because a page with no
 * datetime fields at all still has to clear the previous page's orphan.
 */
function __sweepOrphans__() {
    __pickers = __pickers.filter(function (fp) {
        if (fp && fp.input && fp.input.isConnected) return true;
        try { if (fp && typeof fp.destroy === 'function') fp.destroy(); } catch (e) { /* already torn down */ }
        return false;
    });
}

window.addEventListener("load", __sweepOrphans__);

window.addEventListener("load.form_type", function () {

    __sweepOrphans__();

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

        // flatpickr() returns the instance, or an array of them when the
        // selector matched several elements - normalise so __sweepOrphans__
        // always has a flat list to walk.
        var created = flatpickr("#"+id, datetimepicker);
        if (created) __pickers = __pickers.concat(created);

        $("#"+id).removeAttr("readonly");
    }));
});