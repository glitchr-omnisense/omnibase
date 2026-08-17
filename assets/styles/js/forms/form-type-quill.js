
import '@glitchr/quill';

// import hljs from 'highlight.js';
import Quill from 'quill';
// import { ImageDrop } from 'quill-image-drop-module';
// import { ImageResize } from 'quill-image-resize-module';
 
export default Quill;

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-wysiwyg-field]").forEach((function (el) {

        var id = el.getAttribute("data-wysiwyg-field");

        var placeholder = el.getAttribute("data-wysiwyg-placeholder");
        var placeholderHTML = el.getAttribute("data-wysiwyg-placeholder");

        // "load.form_type" is dispatched globally on every lazy load (a
        // collection's "load more" elsewhere on the page, ...), and this
        // querySelectorAll is unscoped, so a re-fire used to run this whole
        // block again on an already-initialized editor. The old code only
        // removed the STALE TOOLBAR here ("avoid toolbar duplication") and
        // then fell straight through to `new Quill(...)` again a few lines
        // down anyway - a second Quill instance bound to the same
        // container, with its own `.ql-editor` surface and its own
        // 'text-change' listener both writing to the same hidden input.
        // Skipping the whole re-init (not just the toolbar remnant) is the
        // actual fix - same class of bug already found and fixed for
        // flatpickr in form-type-datetimepicker.js.
        var editorId = id+"_editor";
        if ($('#'+editorId).hasClass("ql-container")) return;

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

        var quill = JSON.parse(el.getAttribute("data-wysiwyg-options")) || {};

            // TBI: HTML replacement is in conflict with soft-break line..
            quill.modules.syntax = false;
            // quill.modules.toolbar.push(["html"]);
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

            // quill.modules.toolbar = {
            //     container: quill.modules.toolbar,
            //     handlers: {
            //         'html': function() {

            //             var quillEditor = $("#"+editorId).find(".ql-editor");
            //             var quillToolbar = $("#"+editorId).parent().find(".ql-toolbar");

            //             if(disableHTML) {

            //                 console.log(quillEditor);
            //                 quillContent = quillEditor[0].textContent;
            //                 quillContent = quillContent.replaceAll(/<\/p>\n*/ig, "</p>");
            //                 quillContent = quillContent.replaceAll(/<\/h([1-6])>\n*/ig, "</h$1>");
            //                 quillContent = quillContent.replaceAll(/<\/pre>\n*/ig, "</pre>");
            //                 quillContent = quillContent.replaceAll(/<br\/?>/ig, "<br>\n");
            //                 console.log(quillEditor[0].innerHTML);
            //                 console.log($("#"+editorId).find(".ql-editor"));
            //                 quillEditor[0].innerHTML = quillContent;
            //                 quillEditor.toggleClass("ql-toolbar-html-only");
            //                 quillToolbar.toggleClass("ql-toolbar-html-only");
            //                 if(placeholder) quillEditor.css("placeholder", placeholder);

            //             } else {

            //                 console.log(quillEditor);
            //                 quillContent = quillEditor[0].innerHTML;
            //                 if(quillContent == "<p><br></p>") quillContent = "";
            //                 quillContent = quillContent.replaceAll("<br>", "<br>\n");
            //                 quillContent = quillContent.replaceAll("</p>", "</p>\n\n");
            //                 quillContent = quillContent.replaceAll("</pre>", "</pre>\n\n");
            //                 quillContent = quillContent.replaceAll(/<\/h([1-6])>/ig, "</h$1>\n\n");

            //                 quillEditor[0].textContent = quillContent;
            //                 quillEditor.toggleClass("ql-toolbar-html-only");
            //                 quillToolbar.toggleClass("ql-toolbar-html-only");
            //                 if(placeholderHTML) quillEditor.css("placeholder", placeholderHTML);
            //             }

            //             disableHTML = !disableHTML;
            //         }
            //     }
            // };

        var quillContent = el.innerHTML.trim().replaceAll('/^<p><br><\/p>/ig', "").replaceAll("<br>", "<br>\n");
            el.innerHTML = quillContent;

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
    }));
});