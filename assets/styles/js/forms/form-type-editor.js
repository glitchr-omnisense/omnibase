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
// import Undo from 'editorjs-undo';

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

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-editor-field]").forEach((function (el) {

        var id = el.getAttribute("data-editor-field");
        var value = $("#"+id).val();

        var editorId = id+"_editor";         
        var options = JSON.parse(el.getAttribute("data-editor-options")) || {};

        var endpointByFile = el.getAttribute("data-editor-upload-file"); 
        var endpointByUrl = el.getAttribute("data-editor-upload-url"); 
        var endpointByUser = el.getAttribute("data-editor-upload-user");
        var endpointByThread = el.getAttribute("data-editor-upload-thread");
 
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

                // mention: { 

                //     class: Mention,
                //     config: {

                //         endpoints: {
                //             'arobase': endpointByUser,
                //             'hashtag': endpointByThread
                //         },

                //         data: {
                //             'arobase': {},
                //             'hashtag': {}
                //         },

                //     }
                // },
                
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
            // onReady : () => { new Undo({ editor }); },
            onChange: () => { editor.save().then(onSave); }
        });

        var editor = new EditorJs(options);
        if(options.readOnly ?? false) {
            $(el).addClass("read-only");
        }

    }));
});