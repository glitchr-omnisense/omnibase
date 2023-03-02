
import Cropper from 'cropperjs';

window.addEventListener("load.form_type", function () {

    var cropperSource = {};

    document.querySelectorAll("[data-cropper-field]").forEach((function (el) {

        var cropper;
        var cropperOptions = JSON.parse(el.getAttribute("data-cropper") || "");

        var id    = el.getAttribute("data-cropper-field");
        var quadrant = $("#"+el.getAttribute("data-cropper-quadrant"));
        var positions = JSON.parse(el.getAttribute("data-cropper-positions"));

        var image = document.querySelector("#"+id+"_image");
        var naturalWidth, naturalHeight;

        var initCropper = function() {

            $("#"+id+"_loader").remove()
            $("#"+id+"_image").removeClass("hidden");
            $("#"+id+"_actions").removeClass("hidden");
        };

        var updateCropper = function() {

            // Expand on negative value
            var data  = cropper.getData();
            var x  = ($("#"+id+"_x").val());
            var x0 = (data["x"]);

            var width = $("#"+id+"_width").val();
            if(x < 0) width = Math.round(width) + Math.abs(x-x0);

            var y  = ($("#"+id+"_y").val());
            var y0 = (data["y"]);

            var height = $("#"+id+"_height").val();
            if(y  < 0) height = Math.round(height) + Math.abs(y-y0);

            data = extractData();
            data["width"]  = width;
            data["height"] = height;

            cropper.setData(data);

            // Squeeze action
            if(Math.round(cropper.getData()["x"]) != Math.round(x) && x > 0)
                data["width"]  = x > naturalWidth  ? 0 : data["width"]  - Math.abs(x-x0);

            if(Math.round(cropper.getData()["y"]) != Math.round(y) && y > 0)
                data["height"] = y > naturalHeight ? 0 : data["height"] - Math.abs(y-y0);

            data["width"]  = Math.min(data["width"], naturalWidth);
            data["height"] = Math.min(data["height"], naturalHeight);

            cropper.setData(data);
        }

        // Make sure the form is within boundaries
        var moveCropper = function(event) {

            $("#"+id+"_x").val(Math.round(event.detail.x));
            $("#"+id+"_y").val(Math.round(event.detail.y));
            $("#"+id+"_width").val(Math.round(event.detail.width));
            $("#"+id+"_height").val(Math.round(event.detail.height));
            $("#"+id+"_rotate").val(Math.round(event.detail.rotate));
            $("#"+id+"_scaleX").val(Math.round(event.detail.scaleX));
            $("#"+id+"_scaleY").val(Math.round(event.detail.scaleY));

            $("#"+id+"_x0").val(event.detail.x/naturalWidth);
            $("#"+id+"_y0").val(event.detail.y/naturalHeight);
            $("#"+id+"_xP").val(0.5);
            $("#"+id+"_yP").val(0.5);
            $("#"+id+"_width0").val(event.detail.width/naturalWidth);
            $("#"+id+"_height0").val(event.detail.height/naturalHeight);
        };

        cropperOptions["crop"]  = "crop"  in cropperOptions ? Function('return ' + cropperOptions["crop"] )() : moveCropper;
        cropperOptions["build"] = "build" in cropperOptions ? Function('return ' + cropperOptions["build"])() : updateCropper;
        cropperOptions["ready"] = "ready" in cropperOptions ? Function('return ' + cropperOptions["ready"])() : initCropper;

        function extractData()
        {
            var x      = ($("#"+id+"_x").val());
            var y      = ($("#"+id+"_y").val());
            var width  = ($("#"+id+"_width").val());
            var height = ($("#"+id+"_height").val());
            var rotate = ($("#"+id+"_rotate").val());
            var scaleY = ($("#"+id+"_scaleY").val());
            var scaleX = ($("#"+id+"_scaleX").val());

            var data = {};
                data["x"]      = Math.max(0, Math.min(x     , naturalWidth));
                data["y"]      = Math.max(0, Math.min(y     , naturalHeight));
                data["width"]  = Math.max(0, Math.min(width , naturalWidth));
                data["height"] = Math.max(0, Math.min(height, naturalHeight));
                data["rotate"] = rotate % 360 || 0;
                data["scaleY"] = scaleY || 1;
                data["scaleX"] = scaleX || 1;

            return data;
        }

        var undo;
        var reader = new FileReader();
            reader.onloadend = e => {

                image.src = e.target.result;
                image.onload = function () {

                    //
                    // Set initial dimensions
                    naturalWidth  = parseInt(image.width);
                    naturalHeight = parseInt(image.height);

                    var x0      = $("#"+id+"_x0"     ).val();
                    var y0      = $("#"+id+"_y0"     ).val();
                    var width0  = $("#"+id+"_width0" ).val();
                    var height0 = $("#"+id+"_height0").val();

                    var isEmpty = !x0 && !y0 && !width0 && ! height0;
                    $("#"+id+"_x"     ).val(isEmpty ? 0             : Math.round(     x0 <= 1 ?      x0 * naturalWidth  : x0     ));
                    $("#"+id+"_y"     ).val(isEmpty ? 0             : Math.round(     y0 <= 1 ?      y0 * naturalHeight : y0     ));
                    $("#"+id+"_width" ).val(isEmpty ? naturalWidth  : Math.round( width0 <= 1 ?  width0 * naturalWidth  : width0 ));
                    $("#"+id+"_height").val(isEmpty ? naturalHeight : Math.round(height0 <= 1 ? height0 * naturalHeight : height0));


                    //
                    // Extract data out of fields
                    cropperOptions["data"] = extractData();
                    undo = cropperOptions["data"];

                    cropper = new Cropper(image, cropperOptions);
                    $("#"+id+"_x"     ).off("input.cropper").on("input.cropper", updateCropper);
                    $("#"+id+"_y"     ).off("input.cropper").on("input.cropper", updateCropper);
                    $("#"+id+"_width" ).off("input.cropper").on("input.cropper", updateCropper);
                    $("#"+id+"_height").off("input.cropper").on("input.cropper", updateCropper);
                    $("#"+id+"_scaleX").off("input.cropper").on("input.cropper", updateCropper);
                    $("#"+id+"_scaleY").off("input.cropper").on("input.cropper", updateCropper);
                    $("#"+id+"_rotate").off("input.cropper").on("input.cropper", updateCropper);

                    var aspectRatioButtons = $("#"+id+"_actions button[data-aspect-ratio]");
                        aspectRatioButtons.each(function() {

                            var labelId = $(this).data("labelledby");
                            var label = $("#" + labelId).val();

                            var aspectRatio = $(this).data("labelledby-value");
                            if (aspectRatio === label)
                                cropper.setAspectRatio($(this).data("aspect-ratio"));
                        });

                        aspectRatioButtons.off("click.cropper").on("click.cropper", function() { // Do not inline ("this")

                            cropper.setAspectRatio(parseFloat($(this).data("aspect-ratio")));
                            var offset = positions[$(quadrant).val()] || "center center";
                            var data = cropper.getData();

                            var labelId = $(this).data("labelledby");
                            var label = $("#" + labelId);

                            var labelReplaceable = false;
                            if (label.length) {

                                aspectRatioButtons.each(function(i, btn) {

                                    if(labelReplaceable) return;
                                    if($(btn).data("labelledby") !== labelId) return;

                                    labelReplaceable = $(btn).data("labelledby-value").toLowerCase() == label.val().toLowerCase() || !label.val();
                                });
                            }

                            if (labelReplaceable)
                                label.val($(this).data("labelledby-value")).change();

                            switch(offset) {
                                case "center center": break;
                                case "center top":
                                    data["y"] = 0;
                                    break;
                                case "center bottom":
                                    data["y"] = naturalHeight-data;
                                    break;
                                case "left center":
                                    data["x"] = 0;
                                    break;
                                case "right center":
                                    data["x"] = naturalWidth;
                                    break;

                                case "left top":
                                    data["x"] = 0;
                                    data["y"] = 0;
                                    break;
                                case "right bottom":
                                    data["x"] = naturalWidth;
                                    data["y"] = naturalHeight;
                                    break;
                                case "right top":
                                    data["x"] = naturalWidth;
                                    data["y"] = 0;
                                    break;
                                case "left bottom":
                                    data["x"] = 0;
                                    data["y"] = naturalHeight;
                                    break;
                            }

                            cropper.setData(data);
                        });

                    // $("#"+id+"_x"     ).removeAttr("disabled");
                    // $("#"+id+"_y"     ).removeAttr("disabled");
                    // $("#"+id+"_width" ).removeAttr("disabled");
                    // $("#"+id+"_height").removeAttr("disabled");
                    // $("#"+id+"_scaleX").removeAttr("disabled");
                    // $("#"+id+"_scaleY").removeAttr("disabled");
                    // $("#"+id+"_rotate").removeAttr("disabled");

                    $("#"+id+"_actions button[data-cropper-reset]")
                        .off("click.cropper")
                        .on("click.cropper", function() { // Do not inline (this)

                            cropper.setAspectRatio("NAN");
                            cropper.setData(undo);
                        });
                };
            }

            if(image.src.startsWith("data:")) return;

            //
            // Optimized image loading.
            function loadCropper(image) {

                cropperSource[image.src] = cropperSource[image.src] || new Promise(function (resolve, reject) {

                    var xhr = new XMLHttpRequest();
                        xhr.open('GET', image.src, true);
                        xhr.responseType = 'blob';
                        xhr.onloadend = function (e) {

                            if (xhr.status >= 200 && xhr.status < 300) resolve({status:xhr.status, statusText: xhr.statusText, response:this.response});
                            else reject({ status: xhr.status, statusText: xhr.statusText});
                        };

                        xhr.onerror = function () { reject({status: xhr.status,statusText: xhr.statusText}); };
                        xhr.send();

                });
            }

            loadCropper(image);

            cropperSource[image.src].then( (xhr) => reader.readAsDataURL(xhr.response) );
                /*.catch(function(xhr) { // CATCH ISSUE LOADING IMAGE.. TO DO LATER IF REQUIRED

                    $("#"+id+"_loader").html(errMsg);
                    $("#"...).on(function() {
                        loadCropper(image);
                    })
                });*/

            //
            // Fix refresh of cropper container on resize...
            let doIt;
            window.addEventListener('resize', () => {
                clearTimeout(doIt);
                if(cropper) cropper.disable()
                doIt = setTimeout(() => {
                    if(cropper) cropper.enable()
                }, 100);
            })
    }));
});