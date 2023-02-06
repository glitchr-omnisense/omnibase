

// NB: Collapse must be loaded in app.js for every layout
// ISSUE WITH COLLAPSE.... Not closing when an other istance of collapse has been started
import Collapse from 'bootstrap/js/dist/collapse';

window.addEventListener("load.array_type", function () {

    var updateCollectionItemCssClasses = function (e) {

        if (null !== e) {

            var t = e.querySelectorAll(".form-array-item");
            t.forEach(function (e) {
                return e.classList.remove("form-array-item-first", "form-array-item-last")
            });

            var o = t[0];
            if (void 0 !== o) {
                o.classList.add("form-array-item-first");
                var l = t[t.length - 1];
                void 0 !== l && l.classList.add("form-array-item-last")
            }
        }
    }

    var initAction = function(e) {

        var form = e.closest("form");
        var formButtons = form.querySelectorAll('button[type="submit"]');

        var buttons = new Set();
        $(formButtons).each(function() { buttons.add(this); });
        $(submitButtons).each(function() { if(this.form == form) buttons.add(this); });
        buttons = Array.from(buttons);

        $(buttons).off("click.array.submit");
        $(buttons).on("click.array.submit", function () {

            var invalidRequired = $(':required:invalid', form);
            if (invalidRequired.length)
                $(invalidRequired[0].closest(".accordion-collapse")).collapse("show");
        });
    }

    var deleteAction = function (e) {

        $(e).off("click.array.remove-entry");
        $(e).on("click.array.remove-entry", (function () {

            var o = e.closest("[data-array-field]");
            var f = e.closest(".form-array-item");

            var deleteFn = function() {

                $(this).remove();

                var l = o.dataset.numItems = $(o).find(".form-array-item").length;
                if (l == 0) {

                    var arrayItems = $(o).find(".form-array-items")[0] || undefined;
                    if (arrayItems)
                        arrayItems.insertAdjacentHTML("beforebegin", o.dataset.emptyArray)

                    $(arrayItems).remove();
                }

                $(window).trigger("array.item-removed");
                updateCollectionItemCssClasses(o);
            };

            $(o).find(f).on('hidden.bs.collapsed', deleteFn);
            setTimeout(function() { $(o).find(f).each(deleteFn); }, 500);

            $(o).find(f).each(function() {

                var collapsed = $(this).find(".accordion-button").hasClass("collapsed");
                if (collapsed) $(this).trigger("hidden.bs.collapse");
                else $(this).find(".accordion-button").trigger("click");
            });

            $(o).find(f).collapse("hide");
        }));
    };

    var addAction = function(e) {

        $(e).off("click.array.add-entry");
        $(e).on("click.array.add-entry", function () {

            var o = e.closest("[data-array-field]");

            var l = parseInt(o.dataset.numItems),
                c = e.parentElement.querySelector(".array-empty");

            null !== c && (c.outerHTML = '<div class="form-array-items"></div>');

            var i = o.dataset.formTypeNamePlaceholder,
                n = new RegExp(i + "__label__", "g"),
                a = new RegExp(i, "g"),
                s = o.dataset.prototype.replace(n, l).replace(a, l++);

            o.dataset.numItems = l;

            var d = ".form-array-items";
            var r = o.querySelector(d);

            r.insertAdjacentHTML("beforeend", s);

            updateCollectionItemCssClasses(o);
            var m = r.querySelectorAll(".form-array-item"),
                u = m[m.length - 1];

            $(u).find(".accordion-button").removeClass("collapsed");
            $(u).find(".accordion-collapse").addClass("show")
            $(o).addClass("processed");

            $(u).collapse("show");

            $(document).trigger("array.item-added");

            document.querySelectorAll("button.form-array-delete-button").forEach(deleteAction);
        });
    }

    var submitButtons = document.querySelectorAll('button[type="submit"]');
    document.querySelectorAll("form .form-array").forEach(initAction);
    document.querySelectorAll("button.form-array-add-button").forEach(addAction);
    document.querySelectorAll("button.form-array-delete-button").forEach(deleteAction);

    $(document).off("array.item-added");
    $(document).on ("array.item-added", function() {

        dispatchEvent(new Event("load.form_type"));
        document.dispatchEvent(new Event("load.array_type"));
    });
});