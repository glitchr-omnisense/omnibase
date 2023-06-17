import Sortable from 'sortablejs';
import Dropzone from 'dropzone';

if (typeof Dropzone !== 'undefined')
    Dropzone.autoDiscover = false;

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-file-field]").forEach((function (el) {

        var id       = el.getAttribute("data-file-field");

        var dropzone    = $(el).data('file-dropzone');

        var dropzoneEl  = $("#"+id+"_dropzone");
        var entryIdList = dropzoneEl.data("entry-id") ?? [];

        var pathLinks = dropzoneEl.data("file-path-links") ?? {};
        var clippable      = dropzoneEl.data("file-clippable"      ) ?? {};
        var downloadLinks  = dropzoneEl.data("file-download-links" ) ?? {};

        var lightboxPattern  = dropzoneEl.data("file-lightbox"  ) ?? "";
        var downloadPattern  = dropzoneEl.data("file-download"  ) ?? "";
        var clipboardPattern = dropzoneEl.data("file-clipboard" ) ?? "";
        var gotoPattern      = dropzoneEl.data("file-goto"      ) ?? "";
        var deletePattern    = dropzoneEl.data("file-delete"    ) ?? "";

        var lightboxOptions = $(el).data("file-lightbox-options") || null;
        if (lightboxOptions) lightbox.option(lightboxOptions);

        function updateMetadata(el = $("#"+id), nFiles)
        {
            var id = $(el).attr("id");

            var maxFiles = parseInt(dropzoneEl.data("file-max-files")) || undefined;
            var remainingFiles = maxFiles - nFiles;

            var counter = "";
                    if(nFiles < 1) counter = dropzoneEl.data("file-counter[none]"    ).replace("{0}", nFiles);
            else if(nFiles < 2) counter = dropzoneEl.data("file-counter[singular]").replace("{0}", nFiles);
            else                counter = dropzoneEl.data("file-counter[plural]"  ).replace("{0}", nFiles);

            var counterMax = "";
            if(!isNaN(maxFiles)) {

                     if(remainingFiles < 1) counterMax = dropzoneEl.data("file-counter-max[none]"    ).replace("{0}", remainingFiles);
                else if(remainingFiles < 2) counterMax = dropzoneEl.data("file-counter-max[singular]").replace("{0}", remainingFiles);
                else                        counterMax = dropzoneEl.data("file-counter-max[plural]"  ).replace("{0}", remainingFiles);
            }

            $("#"+id+"_metadata").html(counter+" "+counterMax);
        }

        if(dropzone) {

            Dropzone.confirm = function (question, accepted, rejected) { // Only the last dropzone modal is used...

                $('#'+id+'-text').html(question);
                $('#'+id+'-modal').modal('show');
                $("#"+id+"-dismiss").on("click", function (e) {
                    $('#'+id+'-modal').modal('hide');
                });

                $("#"+id+"-confirm").on("click", function (e) {

                    $('#'+id+'-modal').modal('hide');
                    accepted();
                });

                $("#"+id+"-cancel").on("click", function (e) {

                    $('#'+id+'-modal').modal('hide');
                    reject();
                });

                $("#"+id+"-close").on("click", function (e) {

                    $('#'+id+'-modal').modal('hide');
                    reject();
                });
            };

            var el       = document.getElementById(id+"_dropzone");
            var sortable = $(el).data("file-sortable");
            var ajax     = el.getAttribute("data-file-ajax");

            var paths       = JSON.parse(el.getAttribute("data-file-path-links")) ?? {};

            dropzone.init = function() {

                // Initialize existing pictures
                var val = $('#'+id).val();
                    val = (!val || val.length === 0 ? [] : val.split('|'));

                // Hide loading spinner if no value
                if(val.length === 0) $("#"+id+"_loader").hide();

                var arr = [];
                $.each(val, function(key, path) {

                    const isUUID = /^[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}$/gi;
                    if(isUUID.test(path)) arr.push({path: paths[path] ?? ajax+"/"+path, uuid:path});
                    else arr.push({path:path});
                });

                Promise.all(arr).then(function(val){

                    $.each(val, function(key,file) {

                        var path = file.path;

                        var id = parseInt(key)+1;
                        var uuid = file.uuid ?? path.substring(path.lastIndexOf('/') + 1);

                        var entryId = entryIdList[uuid] ?? null;
                        var mock = {status: 'existing', name: '#'+id, path:path, entryId: entryId, uuid: uuid};

                        editor.files.push(mock);

                        if(path === "") path = "./bundles/base/images.svg";
                        editor.displayExistingFile(mock, path);

                        updateMetadata(this.id, editor.files.length);
                    });
                });

                this.on('error', function(file, response) {

                    var errorMessage = $(file.previewElement).find(".dz-error-message");
                    if (errorMessage.length == 0) return;

                    errorMessage.css("opacity", 1);
                    setTimeout(function() {
                        errorMessage[0].style.removeProperty('opacity');
                    }, 2000);
                });

                this.on('success', function(file, response) {

                    file.serverId = response;

                    var previewList = $('#'+id+'_dropzone .dz-preview');
                    var preview = $(previewList)[previewList.length-1];
                    if(file.status !== "existing")
                        $(preview).data("uuid", file.uuid = file.serverId['uuid']);

                    var val = $('#'+id).val();
                        val = (!val || val.length === 0 ? [] : val.split('|'));

                    val.push(file.serverId['uuid']);
                    $('#'+id).val(val.join('|'));

                    $(preview).find(".dz-overlay").remove();
                    updateMetadata(this.id, val.length);
                });

                function updatePositions(files) 
                {
                    var queue = [];
                    $('#'+id+'_dropzone .dz-preview').each(function(file) {

                        var el = this;
                        var uuid = undefined;
                        $(files).each(function(i) {

                            if(el.isEqualNode(this.previewElement))
                                uuid = this.uuid;
                        });

                        if (uuid) queue.push(uuid);
                    });

                    $('#'+id).val(queue.join('|'));
                }

                function findDuplicates(files, file, dataURL = undefined)
                {
                    var _i, _len;
                    for (_i = 0, _len = files.length; _i < _len - 1; _i++) {

                        if(files[_i] === file) continue; // Exception for the file itself..
                        if(files[_i].status == "existing") {

                            var img = getImage(files[_i].uuid);
                            if(img !== undefined) {

                                if(files[_i].size === file.size && file.uuid == files[_i].uuid && img.src.toString() === dataURL)
                                    return file;
                            }

                        } else {

                            if(files[_i].size === file.size && files[_i].lastModified.toString() === file.lastModified.toString())
                                return file;
                        }
                    }

                    return undefined;
                }

                this.on("thumbnail", function(file, e) {

                    $(file.previewTemplate).find(".dz-overlay").remove();

                    if (e.type == "error") {

                        e.target.src = "./bundles/base/images/image.svg";
                    
                    } else {

                        var duplicateFile = findDuplicates(this.files, file, e);
                        if (duplicateFile) this.removeFile(duplicateFile);
                    }

                    updatePositions(this.files);
                });

                this.on('removedfile', function(file) {

                    // Max files must be updated based on existing files
                    if (file.status == 'existing' && editor.options.maxFiles != null) editor.options.maxFiles += 1;
                    else if (file.serverId) $.post(ajax+"/"+file.serverId['uuid']+'/delete');

                    updateMetadata(this.id, val.length);
                    updatePositions(this.files);
                });

                this.on("addedfile", function(file) {

                    var previewList = $('#'+id+'_dropzone .dz-preview');
                    var preview = $(previewList)[previewList.length-1];

                    $(preview).append($("<div class='dz-overlay dz-loader'><span class='loader-spinner'></span></div>"));

                    // Add UUID to preview for existing files (these are not triggering "success" event)
                    
                    if(file.status == "existing") {

                        $(preview).find(".dz-filename").remove();
                        $(preview).find(".dz-size").remove();
                        $(preview).find(".dz-remove").remove();

                        $(preview).data("uuid", file.uuid);

                        $(preview).find(".dz-details").append($("<div class='dz-tools'></div>"));

                        var span = $(preview).find(".dz-details .dz-tools")[0];

                        var counter = 1;

                        span.innerHTML = "";
                        if(pathLinks[file.uuid] != null) {
                            
                            if(lightboxPattern) span.innerHTML += lightboxPattern.replaceAll("{0}", pathLinks[file.uuid]);
                            else  span.innerHTML += gotoPattern.replaceAll("{0}", pathLinks[file.uuid]);

                        } else {
                            span.innerHTML += "<i class='blank-space'></i>";
                        }
                        
                        var _href = dropzoneEl.data("file-href");
                        if(counter < 4 && deletePattern) {
                            span.innerHTML += deletePattern;
                            counter++;
                        }

                        if(counter < 4 && file.entryId && _href) {
                            span.innerHTML += gotoPattern.replaceAll("{0}", _href).replaceAll("{0}", file.entryId);
                            counter++;
                        }

                        if(counter < 4 && (clippable[file.uuid] ?? false)) {
                            span.innerHTML = clipboardPattern.replaceAll("{0}", pathLinks[file.uuid] || file.path) + span.innerHTML;
                            counter++;
                        }

                        if(counter < 4) {
                            span.innerHTML = downloadPattern.replaceAll("{0}", downloadLinks[file.uuid] || file.path) + span.innerHTML;
                            counter++;
                        }

                        while(counter++ < 4)
                            span.innerHTML += "<i class='blank-space'></i>";
                    }

                    // Replacement remove button
                    var _this = this;
                    $(preview).find("[data-dz-remove]").on("click", function () {

                        if (!_this.options.dictRemoveFileConfirmation)
                            return _this.removeFile(file);

                        $(dropzoneEl).data("dz-select", file.uuid);
                        return Dropzone.confirm(_this.options.dictRemoveFileConfirmation, function() {

                            if(file.uuid != dropzoneEl.data("dz-select")) return false;
                            _this.removeFile(file);

                        });
                    });
                    
                    updatePositions(this.files);
                });

                function getImage(fileUUID)
                {
                    var image = undefined;
                    $('#'+id+'_dropzone .dz-preview').each(function (count, el) {

                        var uuid = $(el).data('uuid');
                        if(fileUUID == uuid) image = $(el).find(".dz-image img")[0] || undefined;
                    });

                    return image;
                }

                // Sortable drag-and-drop
                Array.prototype.insert = function(i,...rest) { this.splice(i,0,...rest); return this; }
                if(sortable) {

                    this.on('dragend', function() {
                        updatePositions(this.files);
                    });
                }
            };

            var editor = dropzoneEl[0].dropzone;
            if (editor === undefined)
                editor = new Dropzone("#"+id+"_dropzone", dropzone);

            if(sortable)
                var sortable = new Sortable(document.getElementById(id+'_dropzone'), {draggable: '.dz-preview'});

        } else {

            var fileType  = $('#'+id+'_file');
            var rawType   = $('#'+id+'_raw');
            var deleteBtn = $('#'+id+'_deleteBtn');

            if (fileType.attr("required") === "required" && fileType.val() === '')
                rawType.attr("required", "required")

            deleteBtn.on('click', function() {

                rawType.val('');
                deleteBtn.css('display', 'none');

                if (fileType.attr("required") === "required")
                    rawType.attr("required", "required")
            });

            rawType.on('change', function() {
                if( rawType.val() !== '') deleteBtn.css('display', 'flex');
                else deleteBtn.css('display', 'none');
            });
        }

    }));
});