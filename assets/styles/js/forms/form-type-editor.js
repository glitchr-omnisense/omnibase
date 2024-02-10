import EditorJs from '@editorjs/editorjs';
import Embed from '@editorjs/embed';
import Warning from '@editorjs/warning';
import NestedList from '@editorjs/nested-list';
import Checklist from '@editorjs/checklist';
import Alert from 'editorjs-alert';
import Table from '@editorjs/table';
import Marker from '@editorjs/marker';
import InlineCode from '@editorjs/inline-code';
import Underline from '@editorjs/underline';
import CodeTool from 'editorjs-code-highlight';
import Quote from '@editorjs/quote';
// import Undo from 'editorjs-undo';

import Header from 'editorjs-header';
import Paragraph from 'editorjs-paragraph';
import Mention from 'editorjs-mention';
import {ImageTool, ImageToolTune} from 'editorjs-image';

// import YTool from 'y-editorjs';

function json_decode(str) {
    try {
        return JSON.parse(str);
    } catch (e) {
        return undefined;
    }
}

function randid(length)
{
    let result = '';
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;
    let counter = 0;
    while (counter < length) {
      result += characters.charAt(Math.floor(Math.random() * charactersLength));
      counter += 1;
    }
    return result;
}

$(window).off("DOMContentLoaded.edjs");
$(window).on("DOMContentLoaded.edjs", function() {

    $("[data-edjs]").each(function() {

        $(this).removeAttr("edjs");
        $(this).attr("id", $(this).attr("id") ?? "editorjs-"+randid(10));

        edjs(undefined, $(this).attr("id"), this.dataset.edjs);
    });
});

function edjs(inputEl, holderId, value = {}, options = {})
{
    var holder = $("#"+holderId)[0] || undefined;
    if(holder == undefined) return;

    holder.innerHTML = ""; // delete existing editorjs instance

    var options  = {};
    if(holder) JSON.parse(holder.getAttribute("data-editor-options")) || {};

    var endpointByFile    = holder.getAttribute("data-editor-upload-file") || undefined;
    var endpointByUrl     = holder.getAttribute("data-editor-upload-url")  || undefined;
    var endpointByUser    = holder.getAttribute("data-editor-endpoint-user")    || undefined;
    var endpointByThread  = holder.getAttribute("data-editor-endpoint-thread")  || undefined;
    var endpointByKeyword = holder.getAttribute("data-editor-endpoint-keyword") || undefined;
    
    var data = json_decode(value);
    if (data) Object.assign(options, {data:data});

    var onSave = (savedData) => { if(inputEl != undefined) $(inputEl).val(JSON.stringify(savedData)); }

    Object.assign(options, {
        readOnly: (inputEl == undefined),
        tools: {

            warning: Warning,

            header: {
                class: Header,
                inlineToolbar: ['link', 'mention'],
            },

            paragraph: {

                class: Paragraph,
                inlineToolbar: true,
            },

            // collaborative: {
            //     class: YTool,
            //     inlineToolbar: true,
            // }, 
            
            mention: { 

                class: Mention,
                config: {

                    typingDelay:1000,
                    endpoints: {
                        'arobase': endpointByUser,
                        'hashtag': endpointByKeyword,
                        'dollar': endpointByThread
                    },
                }
            },
            
            imageTune: ImageToolTune,
            image: {
                class: ImageTool,
                tunes: [ 'imageTune' ],
                config: { 
                    accept: 'image/*',
                    endpoints: {
                        byFile: endpointByFile,
                        byUrl: endpointByUrl
                    },
                }
            },

            alert: Alert,
            underline: Underline,
            code: CodeTool,
            marker: {
                class: Marker,
                shortcut: 'CMD+SHIFT+M',
            },

            list: {
                class: NestedList,
                inlineToolbar: true,
            },

            quote: {
                class: Quote,
                inlineToolbar: true,
            },

            checklist: {
                class: Checklist,
                inlineToolbar: true,
            },

            table: {
                class: Table,
            },

            inlineCode: {
                class: InlineCode,
                shortcut: 'CMD+SHIFT+I',
            },

            embed: Embed,
        }
    });

    Object.assign(options, {

        holder  : holderId, 
        onReady : () => { 
            if(data == undefined && value != '') editor.blocks.renderFromHTML(value);
            // if(inputEl != undefined) new Undo({ editor }); // issue 
        },
        onChange: async (api, event) => {

            if(options.readOnly) return;
            editor.save().then(onSave); 
        }
    });

    var editor = new EditorJs(options);
    if(options.readOnly ?? false) {
        $("#"+holderId).addClass("read-only");
    }
}

window.addEventListener("load.form_type", function (el) {

    document.querySelectorAll("[data-editor-field]").forEach((function (el) {

        var id    = el.getAttribute("data-editor-field");

        var input = $("#"+id);
        var value = $("#"+id).val();

        var editorId = id+"_editor";
        edjs(input, editorId, value);
    }));
});
