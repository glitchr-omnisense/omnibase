window.addEventListener("load.form_type", function () {

    $("[data-avatar-field]").each(function () {

        var el = this;
        var id = el.getAttribute("data-avatar-field");
       
        var cropper = el.getAttribute("data-avatar-cropper") || null;
        if (cropper) {

            $(el).find("#"+id+"_file").on("change", function() {

                var display = $("#"+id+"_file")[0].value !== "" ? "flex" : "none";
                $(el).find("#"+id+"_deleteBtn2").css("display", display);
            });

        } else {

            $(el).find("#"+id+"_raw").on("change", function() {

                var display = $("#"+id+"_raw")[0].value ? "flex" : "none";
                $(el).find("#"+id+"_deleteBtn2").css("display", display);
            });
        }

        $(el).find('#'+id+'_figcaption').on('click', function() {
            $(el).find('#'+id+'_raw').trigger("click");
        });
        
        $(el).find("#"+id+"_deleteBtn2").on("click", function() {

            $(el).find("#"+id+"_raw")[0].value = '';
            $(el).find("#"+id+"_deleteBtn").trigger("click");
        });

        $(el).find("#"+id+"_deleteBtn").on("click", function() {

            $(el).find("#"+id+"_raw")[0].value = '';
            $(el).find("#"+id+"_deleteBtn2").css("display", "none");
        });
    });
});