import EditorJs from '@editorjs/editorjs';

function isHTML(str) {
    var a = document.createElement('div');
    a.innerHTML = str;
  
    for (var c = a.childNodes, i = c.length; i--; ) {
      if (c[i].nodeType == 1) return true; 
    }
  
    return false;
  }

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-editor-field]").forEach((function (el) {

        var id = el.getAttribute("data-editor-field");
        var value = el.getAttribute("data-editor-value");
        var placeholder = el.getAttribute("data-editor-placeholder");

        var editorId = id+"_editor";
          
        var options = JSON.parse(el.getAttribute("data-editor-options")) || {};

        if (!isHTML(value)) {
            Object.assign(options, {data:value});    
        }

        Object.assign(options, {
            holder: editorId, 
            placeholder: placeholder,
            onReady: async () => {

                if (isHTML(value))
                  await editor.blocks.renderFromHTML(value);
            },

            onChange:() => { 
                
                editor.save().then((savedData) =>{
                    console.log('salvado',savedData);
                }).catch((error) =>{
                    console.log("fallo al guardar",error)
                })
            }
        });

        var editor = new EditorJs(options);

    }));
});