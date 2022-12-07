
import '@glitchr/quill';

// import hljs from 'highlight.js';
import Quill from 'quill';
// import { ImageDrop } from 'quill-image-drop-module';
// import { ImageResize } from 'quill-image-resize-module';
 
export default Quill;

$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.quill", function () {

        document.querySelectorAll("[data-quill-field]").forEach((function (el) {

            var id = el.getAttribute("data-quill-field");

            var placeholder = el.getAttribute("data-quill-placeholder");
            var placeholderHTML = el.getAttribute("data-quill-placeholder");

            var editorId = id+"_editor";
            if ($('#'+editorId).hasClass("ql-container")) // Quill editor already loaded (avoid toolbar duplication)
                $('#'+editorId).parent().find(".ql-toolbar").remove();

            var disableHTML = false;
            var Delta = Quill.import('delta');
            let Break = Quill.import('blots/break');
            let Embed = Quill.import('blots/embed');

            function lineBreakMatcher() {
                var newDelta = new Delta();
                newDelta.insert({'break': ''});
                return newDelta;
            }

            class SmartBreak extends Break {
                length () { return 1 }
                value  () { return '\n' }
                insertInto(parent, ref) { Embed.prototype.insertInto.call(this, parent, ref) }
            }

            SmartBreak.blotName = 'break';
            SmartBreak.tagName = 'BR'
            Quill.register(SmartBreak, true);
            // Quill.register('modules/imageDrop', ImageDrop);
            // Quill.register('modules/imageResize', ImageResize);

            var quill = JSON.parse(el.getAttribute("data-quill-options")) || {};

                // TBI: HTML replacement is in conflict with soft-break line..
                quill.modules.toolbar.push(["html"]);
                quill.modules.syntax = false;
                // quill.modules.imageResize = {displaySize: true};
                // quill.modules.imageDrop = true;
                quill.modules.clipboard = { matchers: [['BR', lineBreakMatcher]] }
                quill.modules.keyboard = {
                    bindings: {
                        linebreak: {
                            key: 13,
                            shiftKey: true,
                            handler: function (range) {

                            if(disableHTML) return;
                            let currentLeaf = this.quill.getLeaf(range.index)[0]
                            let nextLeaf = this.quill.getLeaf(range.index + 1)[0]

                            this.quill.insertEmbed(range.index, 'break', true, 'user');

                            // Insert a second break if:
                            // At the end of the editor, OR next leaf has a different parent (<p>)
                            if (nextLeaf === null || (currentLeaf.parent !== nextLeaf.parent)) {
                                this.quill.insertEmbed(range.index, 'break', true, 'user');
                            }

                            // Now that we've inserted a line break, move the cursor forward
                            this.quill.setSelection(range.index + 1, Quill.sources.SILENT);
                            }
                        }
                    }
                }

                quill.modules.toolbar = {
                    container: quill.modules.toolbar,
                    handlers: {
                        'html': function() {

                            var quillEditor = $("#"+editorId).find(".ql-editor");
                            var quillToolbar = $("#"+editorId).parent().find(".ql-toolbar");

                            if(disableHTML) {

                                quillContent = quillEditor.text();
                                quillContent = quillContent.replaceAll(/<\/p>\n*/ig, "</p>");
                                quillContent = quillContent.replaceAll(/<\/h([1-6])>\n*/ig, "</h$1>");
                                quillContent = quillContent.replaceAll(/<\/pre>\n*/ig, "</pre>");
                                quillContent = quillContent.replaceAll(/<br\s*\/?>/ig, "");
                                quillEditor.html(quillContent);
                                quillEditor.toggleClass("ql-toolbar-html-only");
                                quillToolbar.toggleClass("ql-toolbar-html-only");
                                if(placeholder) quillEditor.css("placeholder", placeholder);

                            } else {

                                quillContent = quillEditor.html();
                                if(quillContent == "<p><br></p>") quillContent = "";
                                quillContent = quillContent.replaceAll("\n", "<br>\n");
                                quillContent = quillContent.replaceAll("</p>", "</p>\n\n");
                                quillContent = quillContent.replaceAll("</pre>", "</pre>\n\n");
                                quillContent = quillContent.replaceAll(/<\/h([1-6])>/ig, "</h$1>\n\n");


                                quillEditor.text(quillContent);
                                quillEditor.toggleClass("ql-toolbar-html-only");
                                quillToolbar.toggleClass("ql-toolbar-html-only");
                                if(placeholderHTML) quillEditor.css("placeholder", placeholderHTML);
                            }

                            disableHTML = !disableHTML;
                        }
                    }
                };

            var quillEditor = new Quill('#'+editorId, quill);
                quillEditor.on('text-change', function() {

                    var html = $('#'+editorId).find(".ql-editor")[0].innerHTML || "";
                    document.getElementById(id).value = html;
                });

            var length = quillEditor.getLength()
            var text = quillEditor.getText(length - 2, 2)

            // Remove extraneous new lines
            if (text === '\n\n') {
                quillEditor.deleteText(quillEditor.getLength() - 2, 2)
            }

            $('#'+editorId).closest("form").on("submit.quill", function(e) {

                var quillEditor = $("#"+editorId).find(".ql-editor");
                var quillToolbar = $("#"+editorId).parent().find(".ql-toolbar");

                var quillContent = quillEditor.text();
                if(disableHTML) {

                    quillEditor.html(quillContent);
                    quillToolbar.toggleClass("ql-toolbar-html-only");
                    if(placeholder) quillEditor.css("placeholder", placeholder);

                    disableHTML = !disableHTML;
                }

                quillContent = quillEditor.html();

                if (quillContent == "<p><br></p>")
                    $("#"+id).attr("value", "");
            });

            $('#'+editorId).find(".ql-editor").css("min-height", quill["height"]);

            var quillEditor = $("#"+editorId).find(".ql-editor");
            var quillContent = quillEditor.html().replaceAll("<p><br></p>", "").replaceAll("<br>", "\n");
                quillEditor.html(quillContent);
        }));
    });

    $(document).trigger("load.form_type.quill");
});