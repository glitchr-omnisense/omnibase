

// NB: Collapse must be loaded in app.js for every layout
// ISSUE WITH COLLAPSE.... Not closing when an other istance of collapse has been started
import Collapse from 'bootstrap/js/dist/collapse';

window.addEventListener("load.collection_type", function () {

    var updateCollectionItemCssClasses = function (e) {

        if (null !== e) {

            var t = e.querySelectorAll(".form-collection-item");
                t.forEach(function (e) {
                    return e.classList.remove("form-collection-item-first", "form-collection-item-last")
                });

            var o = t[0];
            if (void 0 !== o) {
                o.classList.add("form-collection-item-first");
                var l = t[t.length - 1];
                void 0 !== l && l.classList.add("form-collection-item-last")
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

        $(buttons).off("click.collection.submit");
        $(buttons).on("click.collection.submit", function () {

            var invalidRequired = $(':required:invalid', form);
            if (invalidRequired.length)
                $(invalidRequired[0].closest(".accordion-collapse")).collapse("show");
        });
    }

    var deleteAction = function (e) {

        $(e).off("click.collection.remove-entry");
        $(e).on("click.collection.remove-entry", (function () {

            var o = e.closest("[data-collection-field]");
            var f = e.closest(".form-collection-item");

            var deleteFn = function() {

                $(this).remove();

                var l = o.dataset.numItems = $(o).find(".form-collection-item").length;
                if (l == 0) {

                    var collectionItems = $(o).find(".form-collection-items")[0] || undefined;
                    if (collectionItems)
                        collectionItems.insertAdjacentHTML("beforebegin", o.dataset.emptyCollection)

                    $(collectionItems).remove();
                }

                $(window).trigger("collection.item-removed");
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

        $(e).off("click.collection.add-entry");
        $(e).on("click.collection.add-entry", function () {

            var o = e.closest("[data-collection-field]");

            var l = parseInt(o.dataset.numItems),
                c = e.parentElement.querySelector(".collection-empty");

            null !== c && (c.outerHTML = '<div class="form-collection-items"></div>');

            var i = o.dataset.formTypeNamePlaceholder,
                n = new RegExp(i + "__label__", "g"),
                a = new RegExp(i, "g"),
                s = o.dataset.prototype.replace(n, l).replace(a, l++);

            o.dataset.numItems = l;

            var d = ".form-collection-items";
            var r = o.querySelector(d);

            r.insertAdjacentHTML("beforeend", s);

            updateCollectionItemCssClasses(o);
            var m = r.querySelectorAll(".form-collection-item"),
                u = m[m.length - 1];

            $(u).find(".accordion-button").removeClass("collapsed");
            $(u).find(".accordion-collapse").addClass("show")
            $(o).addClass("processed");

            $(u).collapse("show");

            dispatchEvent(new Event("load.form_type"));

            document.querySelectorAll("button.form-collection-delete-button").forEach(deleteAction);
        });
    }

    var submitButtons = document.querySelectorAll('button[type="submit"]');
    document.querySelectorAll("form .form-collection").forEach(initAction);
    document.querySelectorAll("button.form-collection-add-button").forEach(addAction);
    document.querySelectorAll("button.form-collection-delete-button").forEach(deleteAction);
});