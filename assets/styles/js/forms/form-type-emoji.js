import { createPopup } from '@picmo/popup-picker';
import { autoTheme, darkTheme, lightTheme } from 'picmo';

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-emoji-field]").forEach((function (el) {

        // "load.form_type" is dispatched globally on every lazy load (a
        // collection's "load more" elsewhere on the page, ...), and this
        // querySelectorAll is unscoped - without this guard, every re-fire
        // created another picmo popup AND another native click listener on
        // the same field (native addEventListener doesn't dedupe distinct
        // closures), so one click opened N independent popups at once.
        // Same class of bug already found and fixed for flatpickr in
        // form-type-datetimepicker.js.
        if (el.dataset.emojiInitialized) return;
        el.dataset.emojiInitialized = "1";

        var pickerOptions = {
            theme: autoTheme
        };

        var popupOptions = {
            triggerElement: el,
            referenceElement: el
        };

        const popup = createPopup(pickerOptions, popupOptions);
                popup.addEventListener('emoji:select', event => { el.value = event.emoji; });

        el.addEventListener("click", () => { popup.toggle(); });
    }));
});
