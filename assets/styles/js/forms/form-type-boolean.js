window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-boolean-field]").forEach((function (el) {

        var id = el.getAttribute("data-boolean-field");
        var onCheck        = el.getAttribute("data-boolean-confirmation-check") ?? false;
        var onUncheck      = el.getAttribute("data-boolean-confirmation-uncheck") ?? false;
        var onConfirmation = false;

        $("#"+id).on("click", function (e) {

            var checkbox = $(this);
            if (onConfirmation) onConfirmation = false;
            else {

                if ((checkbox.prop("checked") && onCheck) || (!checkbox.prop("checked") && onUncheck)) {

                    $('#'+id+'-modal').modal('show');
                    e.preventDefault();
                    return false;
                }
            }

            var checkboxBak = !checkbox.prop("checked");

            var toggleUrl  = checkbox.data("toggle-url");
            if (toggleUrl) {

                fetch(toggleUrl + "&newValue=" + checkbox.prop("checked").toString(), {
                    method: "PATCH",
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                }).then((function (t) {
                    checkbox.removeClass("invalid-feedback d-block");
                })).then((function () {})).catch((function () {
                    checkbox.prop("checked", checkboxBak).addClass("invalid-feedback d-block");
                }));
            }
        });

        $("#"+id+"-confirm").on("click", function (e) {

            $('#'+id+'-modal').modal('hide');
            onConfirmation = true;

            $("#"+id).trigger("click");
        });

        $("#"+id+"-cancel").on("click", function (e) {

            $('#'+id+'-modal').modal('hide');
            onConfirmation = false;
        });
        $("#"+id+"-close").on("click", function (e) {

            $('#'+id+'-modal').modal('hide');
            onConfirmation = false;
        });
    }));
});