// Use either jscolor
import "@eastdesire/jscolor";

// Or pickr libraryr.. with one of the following themes
import '@simonwep/pickr/dist/themes/classic.min.css';   // 'classic' theme
import '@simonwep/pickr/dist/themes/monolith.min.css';  // 'monolith' theme
import '@simonwep/pickr/dist/themes/nano.min.css';      // 'nano' theme

// Modern or es5 bundle (pay attention to the note below!)
import Pickr from '@simonwep/pickr';

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-color-field]").forEach((function (el) {

        // "load.form_type" is dispatched globally on every lazy load (a
        // collection's "load more" elsewhere on the page, ...), and this
        // querySelectorAll is unscoped - without this guard, every re-fire
        // stacked another Pickr instance (its own popup DOM + its own
        // document-level listeners) on the same already-initialized field.
        // Same class of bug already found and fixed for flatpickr in
        // form-type-datetimepicker.js; Pickr has no built-in marker to
        // reuse the way flatpickr/Dropzone do, so it's flagged explicitly.
        if (el.dataset.colorInitialized) return;
        el.dataset.colorInitialized = "1";

        el.style.backgroundColor = el.value;

        var pickrOptions = JSON.parse(el.getAttribute("data-color-pickr"));
            pickrOptions["default"] = el.value;

        var pickr = new Pickr(Object.assign({}, pickrOptions));
            pickr.on('change', (color, instance) => {

                var hexa = color.toHEXA().toString();
                if (hexa.length == 7) hexa += 'FF';

                var colorRgba = color.toRGBA();

                el.value = hexa;
                el.style.backgroundColor = hexa;
                el.style.color = (Math.sqrt(
                    0.299 * (colorRgba[0] * colorRgba[0]) +
                    0.587 * (colorRgba[1] * colorRgba[1]) +
                    0.114 * (colorRgba[2] * colorRgba[2])
                ) <= 127.5 && colorRgba[3] > 0.4) ?  '#FFF' : '#000';
            });
    }));
});