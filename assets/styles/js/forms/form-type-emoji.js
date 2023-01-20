import { createPopup } from '@picmo/popup-picker';
import { autoTheme, darkTheme, lightTheme } from 'picmo';

$(window).off("DOMContentLoaded.form_type.emoji");
$(window).on("DOMContentLoaded.form_type.emoji", function () {

    $(window).on("load.form_type.emoji");
    $(window).on("load.form_type.emoji", function () {

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

    $(window).trigger("load.form_type.emoji");
});

$(window).trigger("DOMContentLoaded.form_type.emoji");