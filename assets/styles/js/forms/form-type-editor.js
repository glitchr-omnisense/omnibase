import EditorJs from '@editorjs/editorjs';
import Header from '@editorjs/header';
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
import Undo from 'editorjs-undo';

import Paragraph from 'editorjs-paragraph';
import Mention from 'editorjs-mention';
import {ImageTool, ImageToolTune} from 'editorjs-image';

import YTool from 'y-editorjs';

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

        edjs(undefined, $(this).attr("id"), $(this).data("edjs"));
    });
});

function edjs(id, holder, data = {}, options = {}, endpointByFile = undefined, endpointByUrl = undefined)
{
    if (data) Object.assign(options, {data:data});

    Object.assign(options, {
        readOnly: (id == undefined),
        tools: {    
            header: {
                class: Header,
                inlineToolbar: ['link'],
                config: {
                placeholder: 'Title...'
                }
            },
            paragraph: {
                class: Paragraph,
                inlineToolbar: true,
            },
            
            image: {
                class: ImageTool,
                config: {
                    accept: 'image/*',
                    endpoints: {
                        byFile: endpointByFile,
                        byUrl: endpointByUrl
                    },
                }
            },

            warning: Warning,
            alert: Alert,
            underline: Underline,
            code: CodeTool,
            Marker: {
                class: Marker,
                shortcut: 'CMD+SHIFT+M',
            },
            list: {
                class: NestedList,
                inlineToolbar: true,
            },
            checklist: {
                class: Checklist,
                inlineToolbar: true,
            },
            paragraph: {
                class: Paragraph,
                inlineToolbar: true,
            },
            table: {
                class: Table,
            },
            inlineCode: {
                class: InlineCode,
                shortcut: 'CMD+SHIFT+M',
            },
        }
    });

    Object.assign(options, {
        holder: holder, 

        onChange:() => { 
            
            editor.save().then((savedData) =>{
                $("#"+id).val(JSON.stringify(savedData));
            }).catch((error) =>{
                console.log("Failed to save editor modification.. ", error)
            })
        }
    });

    var editor = new EditorJs(options);
}

window.addEventListener("load.form_type", function (el) {

    document.querySelectorAll("[data-editor-field]").forEach((function (el) {

        var id    = el.getAttribute("data-editor-field");
        var value = $("#"+id).val();

        var editorId = id+"_editor";
        $("#"+editorId)[0].innerHTML = ""; // delete existing editorjs instance

        var options  = JSON.parse(el.getAttribute("data-editor-options")) || {};

        var endpointByFile    = el.getAttribute("data-editor-upload-file") || undefined;
        var endpointByUrl     = el.getAttribute("data-editor-upload-url")  || undefined;

        var endpointByUser    = el.getAttribute("data-editor-endpoint-user")    || undefined;
        var endpointByThread  = el.getAttribute("data-editor-endpoint-thread")  || undefined;
        var endpointByKeyword = el.getAttribute("data-editor-endpoint-keyword") || undefined;
 
        var data = json_decode(value);
        if (data) Object.assign(options, {data:data});

        var onSave = (savedData) => { $("#"+id).val(JSON.stringify(savedData)); }
        Object.assign(options, {
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

            holder  : editorId, 
            onReady : () => { 
                if(data == undefined && value != '') editor.blocks.renderFromHTML(value);
                new Undo({ editor }); 
            },
            onChange: () => { editor.save().then(onSave); }
        });

        var editor = new EditorJs(options);
        if(options.readOnly ?? false) {
            $(el).addClass("read-only");
        }
    }));
});
