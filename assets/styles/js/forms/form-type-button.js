window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-button-field]").forEach((function (el) {

        var id = el.getAttribute("data-button-field");

        var onConfirmation        = el.getAttribute("data-button-confirmation") ?? false;
        if(onConfirmation) {

            var bubbleUp       = el.getAttribute("data-button-confirmation-bubbleup") ?? true;
            $("#"+id+"-request").off("click");
            $("#"+id+"-request").on("click", function (e) {

                if(bubbleUp) $('#'+id+'-modal').appendTo("body").modal('show');
                else $('#'+id+'-modal').modal('show');

                e.preventDefault();

                return false;
            });

            $("#"+id+"-cancel").on("click");
            $("#"+id+"-cancel").on("click", function (e) {

                $('#'+id+'-modal').modal('hide');
            });

            $("#"+id+"-confirm").off("click");
            $("#"+id+"-confirm").on("click", function (e) {

                $('#' + id + '-modal').modal('hide');
                $("#"+id).trigger("click");
            });

        } else {

            $("#"+id+"-request").off("click");
            $("#"+id+"-request").on("click", function (e) {

                $(this).prop("disable", "true");
                $("#"+id).trigger("click");
            });
        }
    }));
});