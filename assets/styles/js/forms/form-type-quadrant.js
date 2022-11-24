$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.quadrant", function () {

        document.querySelectorAll("[data-quadrant-field]").forEach((function (e) {

            var id    = $(e).data("quadrant-field");
            var value = $(e).val();
            $("#"+id+"_matrix button").each(function(k) {

                // If button already initialized.. just skip
                if($("#"+id+"_matrix button.maintain").length) return;

                if ($(this).data("quadrant") === value)
                    $(this).addClass("maintain");
            });

            $("#"+id+"_matrix button").off("click.quadrant");
            $("#"+id+"_matrix button").on("click.quadrant", function() {

                $("#"+id+"_wind").val($(this).data("quadrant"));

                $("#"+id+"_matrix button").removeClass("maintain");
                $(this).addClass("maintain");
            });
        }))
    });

    $(document).trigger("load.form_type.quadrant");
});