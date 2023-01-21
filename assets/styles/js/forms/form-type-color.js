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

        el.style.backgroundColor = el.value;
        var pickr = new Pickr(Object.assign({}, JSON.parse(el.getAttribute("data-color-pickr"))));
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