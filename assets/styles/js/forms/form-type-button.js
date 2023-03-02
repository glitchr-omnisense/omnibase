window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-button-field]").forEach((function (el) {

        var id = el.getAttribute("data-button-field");

        var onConfirmation        = el.getAttribute("data-button-confirmation") ?? false;
        if(onConfirmation) {

            $("#"+id+"-request").on("click", function (e) {

                $('#'+id+'-modal').modal('show');
                e.preventDefault();

                return false;
            });

            $("#"+id+"-cancel").on("click", function (e) {

                $('#'+id+'-modal').modal('hide');
            });

            $("#"+id).on("click", function (e) {

                setTimeout(function() {
                    $('#' + id + '-modal').modal('hide');
                },100);
            });
        }
    }));
});