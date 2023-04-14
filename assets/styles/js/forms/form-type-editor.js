import EditorJs from '@editorjs/editorjs';
import Header from '@editorjs/header';
import ImageTool from '@editorjs/image';
import Undo from 'editorjs-undo';
import Warning from '@editorjs/warning';
import NestedList from '@editorjs/nested-list';
import Checklist from '@editorjs/checklist';
import Alert from 'editorjs-alert';
import Paragraph from 'editorjs-paragraph-with-alignment';
import Table from '@editorjs/table';
import Marker from '@editorjs/marker';
import InlineCode from '@editorjs/inline-code';
import Underline from '@editorjs/underline';
import CodeTool from '@editorjs/code';

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

        var data = json_decode(value);
        if (data) Object.assign(options, {data:data});

        Object.assign(options, {
            tools: {
                warning: Warning,
                            
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
                image: {
                    class: ImageTool,
                    config: {
                        accept: 'image/*',
                        endpoints: {
                            byFile: endpointByFile,
                            byUrl: endpointByUrl
                        },
                    }
                }
            }
        });

        Object.assign(options, {
            holder: editorId, 
            onReady: async () => {

                if (!data) {

                    await editor.blocks.renderFromHTML(value);

                } else {

                    const undo = new Undo({ editor });
                    if(data) undo.initialize(data);
                }

            },

            onChange:() => { 
                
                editor.save().then((savedData) =>{
                    $("#"+id).val(JSON.stringify(savedData));
                }).catch((error) =>{
                    console.log("Failed to save editor modification.. ", error)
                })
            }
        });

        var editor = new EditorJs(options);

    }));
});