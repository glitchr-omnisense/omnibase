$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.array", function () {

        var updateArrayItemCssClasses = function (e) {

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

        document.querySelectorAll("button.form-array-add-button").forEach(function (e) {

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
                updateArrayItemCssClasses(o);
                var m = r.querySelectorAll(".form-array-item"),
                    u = m[m.length - 1];

                u.querySelector(".accordion-button").classList.remove("collapsed");
                o.classList.add("processed");

                $(u).collapse("show");

                $(document).trigger("array.item-added");
            });
        });

        document.querySelectorAll("button.form-array-delete-button").forEach((function (e) {

            $(e).off("click.array.remove-entry");
            $(e).on("click.array.remove-entry", (function () {

                var o = e.closest("[data-array-field]");
                var f = e.closest(".form-array-item");

                $(o).find(f).on('hidden.bs.collapse', function() {

                    $(this).remove();
                    var l = o.dataset.numItems = $(o).find(".form-array-item").length;
                    if (l == 0) {

                        var arrayItems = $(o).find(".form-array-items")[0] || undefined;
                        if (arrayItems)
                            arrayItems.insertAdjacentHTML("beforebegin", o.dataset.emptyArray)

                        $(arrayItems).remove();
                    }

                    $(document).trigger("array.item-removed");
                    updateArrayItemCssClasses(o);
                });

                $(o).find(f).each(function() {

                    var collapsed = $(this).find(".accordion-button").hasClass("collapsed");
                    if (collapsed) $(this).trigger("hidden.bs.collapse");
                    else $(this).find(".accordion-button").trigger("click");
                });

                $(o).find(f).collapse("hide");
            }));
        }));

        $(document).off("array.item-added array.item-removed");
        $(document).on ("array.item-added array.item-removed", function() {
            $(document).trigger("load.form_type.array");
        });
    });

    $(document).trigger("load.form_type.array");
});