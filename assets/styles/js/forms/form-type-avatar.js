$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.avatar", function () {

        document.querySelectorAll("[data-avatar-field]").forEach(function (el) {

            var id = el.getAttribute("data-avatar-field");

            var cropper = el.getAttribute("data-avatar-cropper") || null;
            if (cropper) {

                $("#"+id+"_file").on("change.avatar", function() {

                    var display = $("#"+id+"_file").value !== "" ? "flex" : "none";
                    $("#"+id+"_deleteBtn2").css("display", display);
                });

            } else {

                $("#"+id+"_raw").on("change.avatar", function() {

                    var display = $("#"+id+"_raw").value !== "" ? "flex" : "none";
                    $("#"+id+"_deleteBtn2").css("display", display);
                });
            }

            $('#'+id+'_figcaption').on('click.avatar', function() {
                $('#'+id+'_raw').trigger("click");
            });
            
            $("#"+id+"_deleteBtn2").on("click.avatar", function() {

                $("#"+id+"_raw").value = '';
                $("#"+id+"_deleteBtn").trigger("click");
            });

            $("#"+id+"_deleteBtn").on("click.avatar", function() {

                $("#"+id+"_raw").value = '';
                $("#"+id+"_deleteBtn2").css("display", "none");
            });

        });

    });

    $(document).trigger("load.form_type.avatar");
});

