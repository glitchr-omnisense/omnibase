if (typeof Dropzone !== 'undefined')
    Dropzone.autoDiscover = false;

$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.color", function () {

        document.querySelectorAll("[data-color-field]").forEach((function (el) {

            pickr = new Pickr(Object.assign({}, JSON.parse(el.getAttribute("data-options")))).on('change', (color, instance) => {

                var hexa = color.toHEXA().toString();
                if (hexa.length == 7) hexa += 'FF';

                var colorRgba = color.toRGBA();
                var el = instance.options.el;
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

    $(document).trigger("load.form_type.color");
});