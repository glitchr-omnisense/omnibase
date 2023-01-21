import { createPopup } from '@picmo/popup-picker';
import { autoTheme, darkTheme, lightTheme } from 'picmo';

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-emoji-field]").forEach((function (el) {

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
