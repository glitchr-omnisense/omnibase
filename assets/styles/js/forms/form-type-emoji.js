import { createPopup } from '@picmo/popup-picker';
import { autoTheme, darkTheme, lightTheme } from 'picmo';

$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.emoji", function () {

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

    $(document).trigger("load.form_type.emoji");
});