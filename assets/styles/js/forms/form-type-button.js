window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-button-field]").forEach((function (el) {

        var id = el.getAttribute("data-button-field");
        var confirmation = false;

        var onConfirmation        = el.getAttribute("data-button-confirmation") ?? false;
        if(onConfirmation) {

            $("#"+id).on("click", function (e) {

                if(confirmation) return true;  
                else { 
    
                    $('#'+id+'-modal').modal('show');
                    e.preventDefault();
                }
    
                confirmation = false;
                return false;
            });

            $("#"+id+"-confirm").on("click", function (e) {

                $('#'+id+'-modal').modal('hide');
                confirmation = true;

                $("#"+id).trigger("click");

            });

            $("#"+id+"-cancel").on("click", function (e) {

                $('#'+id+'-modal').modal('hide');
                confirmation = false;

            });
        }
    }));
});