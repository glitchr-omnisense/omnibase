
$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.image", function () {

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0B';

            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + sizes[i];
        }

        document.querySelectorAll("[data-image-field]").forEach((function (el) {

            var id              = el.getAttribute("data-image-field");
            var thumbnail       = el.getAttribute("data-image-thumbnail");
            var ajaxUrl         = el.getAttribute("data-image-ajax");
            var maxSize         = el.getAttribute("data-image-maxsize");
            var maxSizeFeedback = el.getAttribute("data-image-maxsize[feedback]");
            var uploadFailed    = el.getAttribute("data-image-uploadFailed");

            var modal          = $(el).data("image-modal") || {};
            $('#'+id+'_modal').modal(modal);

            var cropper = el.getAttribute("data-image-cropper") ? JSON.parse(el.getAttribute("data-image-cropper")) : undefined;
            if (cropper) {

                var imageCropper;

                // Image processing
                $('#'+id+'_modal').on('shown.bs.modal', function () {
                    imageCropper = new Cropper($('#'+id+'_cropper')[0], cropper);
                }).on('hidden.bs.modal', function () {
                    imageCropper.destroy();
                });

                $('#'+id+'_deleteBtn').on('click', function () {

                    var file = $('#'+id+'_file').val();
                    if(file !== '') $.post(ajaxUrl+"/"+file+"/delete");
                });

                $('#'+id+'_modalClose').on('click', function () {
                    $('#'+id+'_modal_feedback').html("");
                    $('#'+id+'_modal').modal('hide');
                    $('#'+id+'_modalSave').removeAttr("disabled");
                    $('#'+id+'_modalClose').removeAttr("disabled");
                    $('#'+id+'_raw').val("");
                });

                $(document).on('keypress',function(e) {
                    if(e.which == 13 && $('#'+id+'_raw').val() !== '')
                        $('#'+id+'_modalSave').trigger('click');
                });

                $('#'+id+'_modalSave').on('click', function () {

                    $('#'+id+'_modalClose').prop("disabled", true);
                    $('#'+id+'_modalSave').prop("disabled", true);
                    if (imageCropper) {

                        var mimeType = $('#'+id+'_raw')[0].files[0].type;
                        var canvas = imageCropper.getCroppedCanvas();
                        canvas.toBlob(function (blob) {

                            if(blob.size > maxSize) {
                                $('#'+id+'_modal_feedback').html(maxSizeFeedback.replace("{0}", formatBytes(blob.size)));
                                $('#'+id+'_modalClose').removeAttr("disabled");
                                $('#'+id+'_modalSave').removeAttr("disabled");
                                return false;
                            }

                            var formData = new FormData();
                                formData.append('file', blob, $('#'+id+'_raw').val());

                            return $.ajax(ajaxUrl, {
                                method: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,

                                success: function (file) {
                                    var prevFile = $('#'+id+'_file').val();
                                    var prevUuid = prevFile.substring(prevFile.lastIndexOf('/') + 1);
                                    if(prevFile !== '') $.post(ajaxUrl+"/"+prevUuid+"/delete");

                                    $('#'+id+'_modal').modal('hide');

                                    $('#'+id+'_thumbnail')[0].src = canvas.toDataURL(mimeType);
                                    $('#'+id+'_file').val(file.uuid).trigger('change');
                                    $('#'+id+'_raw').val("");

                                    $('#'+id+'_modalClose').removeAttr("disabled");
                                    $('#'+id+'_modalSave').removeAttr("disabled");
                                },

                                error: function () {

                                    $('#'+id+'_thumbnail')[0].src = thumbnail;
                                    $('#'+id+'_modal_feedback').html(uploadFailed);
                                    $('#'+id+'_modalClose').removeAttr("disabled");
                                    $('#'+id+'_modalSave').removeAttr("disabled");
                                }
                            });
                        }, mimeType, 1);
                    }
                });

                $('#'+id+'_thumbnail').on('click.image', function() {
                    if($('#'+id+'_raw').val() === '') $('#'+id+'_raw').click();
                    else $('#'+id+'_modal').modal("show");
                });

                $('#'+id+'_deleteBtn').on('click.image', function() {
                    $('#'+id+'_thumbnail')[0].src = thumbnail;
                    $('#'+id+'_raw').val('');
                    $('#'+id+'_raw').change();
                });

                $('#'+id+'_raw').on('change.image', function(e) {

                    if( $('#'+id+'_raw').val() !== '') {

                        $('#'+id+'_modal').modal("show");
                        $('#'+id+'_figcaption').css('display', 'none');
                        $('#'+id+'_cropper')[0].src = URL.createObjectURL(e.target.files[0]);

                    } else {

                        $('#'+id+'_file').val('');
                        $('#'+id+'_figcaption').css('display', 'flex');
                        $('#'+id+'_cropper')[0].src = thumbnail;
                    }
                });

            } else {

                $('#'+id+'_thumbnail').on('click.image', function() {
                    $('#'+id+'_raw').click();
                });

                $('#'+id+'_raw').on('change.image', function(e) {

                    if( $('#'+id+'_raw').val() !== '') {

                        $('#'+id+'_figcaption').css('display', 'none');
                        $('#'+id+'_thumbnail')[0].src = URL.createObjectURL(e.target.files[0]);

                    } else {

                        $('#'+id+'_file').val('');
                        $('#'+id+'_figcaption').css('display', 'flex');
                        $('#'+id+'_thumbnail')[0].src = thumbnail;
                    }
                });
            }

            var lightboxOptions = $(el).data("image-lightbox") || null;
            $('#'+id+'_preview').on('click.image', function() {

                if (lightboxOptions) $('#'+id+'_lightbox').trigger("click");
                else $('#'+id+'_raw').trigger("click");
            });

            $('#'+id+'_deleteBtn').on('click.image', function() {
                $('#'+id+'_thumbnail')[0].src = thumbnail;
                $('#'+id+'_raw').trigger("change");
            });
        }));
    });

    $(document).trigger("load.form_type.image");
});