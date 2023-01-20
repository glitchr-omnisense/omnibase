
$(window).on("DOMContentLoaded.form_type.attribute").off();
$(window).on("DOMContentLoaded.form_type.attribute", function () {

    $(window).off("load.form_type.attribute");
    $(window).on("load.form_type.attribute", function () {

        document.querySelectorAll("form .form-attribute").forEach(function (e) {

            // Handle dynamic display.. later maybe ?
        });
    });

    $(window).trigger("load.form_type.attribute");
});

$(window).trigger("DOMContentLoaded.form_type.attribute");