import '@glitchr/select2';

function is_dict(v) {
    return typeof v==='object' && v!==null && !(v instanceof Array) && !(v instanceof Date);
}

function highlight_search(text, search) {

    if(!search) return text;
    var reg = new RegExp(search, 'gi');
    return text.replace(reg, function(str) {return '<mark>'+str+'</mark>'});
}

var localCache = {};
var localCacheSelected = {};
var localCacheData = {};

window.addEventListener("load.form_type", function () {

    document.querySelectorAll("[data-select2-field]").forEach((function (el) {

        var term = "";
        var page = "1.0";
        var field = $("#"+el.getAttribute("data-select2-field"));

        // Prevent to keep containers when changing pages using ajax
        $(field).parent().find(".select2-container").remove();

        var defaultTemplate = function(option) {

            if(option.id == null)
                return option.text;

            var dataAttribute = "";
            $(option["data"]).each(function(key, value)
            {
                var value = option["data"][key];
                if(value === undefined) return;

                value = value.replace(/"/g, '\\"');
                dataAttribute += key + "=\"" + value+"\" ";
            });

            var highlight = field.data("select2-highlight") || true;
            var tab   = field.data("select2-tabulation") || "1.75em";
            var depth = option["depth"] || 0;

            var href  = field.data("select2-href") || undefined;
                href = option.id && href !== undefined ? href.replace("{0}", option.id) : undefined;

            if(!is_dict(option.icon)) option.icon = {'class': option.icon};

            var iconAttributes = "";
            $(Object.keys(option["icon"])).each(function(i, key) {

                var value = option["icon"][key];
                if (value === undefined || value === null) return;

                value = value.replace(/"/g, '\\"');
                iconAttributes += key + "=\"" + value+"\" ";
            });

            term = $('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val();

            // A real profile picture (User's avatar, when the backend
            // resolved one) reads better than a generic role icon, and
            // matters most exactly where a plain name is hardest to tell
            // apart at a glance - several authors on the same entity.
            var avatarUrl = option["data"] ? option["data"]["avatar"] : undefined;
            var icon = avatarUrl
                ? '<img class="select2-avatar" src="' + avatarUrl.replace(/"/g, '\\"') + '"> '
                : (iconAttributes ? '<i '+ iconAttributes + '></i> ' : '');
            // The </a> was missing and the <i> unclosed, so the browser
            // auto-closed the anchor around whatever followed - which is why
            // the arrow's hit area bled into the row.
            var externalLink = (href ? '<span class="select2-external"><a target="_blank" rel="noopener" href="'+href+'"><i class=\"fas fa-external-link-square-alt\"></i></a></span>' : '');
            var highlightSearch = option.html ? option.html : (icon + highlight_search(option.text, term) + externalLink);
            var shiftAttribute = ' style="margin-left:calc('+tab+' * '+depth+')" class=\"select2-selection__entry\" '+dataAttribute;

            var $rendered = $('<span '+shiftAttribute+'><span>' + highlightSearch + '</span></span>');

            // The arrow opens the record; it is not a way of choosing it.
            // Select2 selects an option from a click anywhere inside the
            // rendered row, so following the link ALSO added the entry to the
            // field - reported live: one click, two outcomes, and the second
            // one unwanted.
            //
            // Propagation is stopped rather than the default prevented, so the
            // link still opens normally in its new tab. mousedown and mouseup
            // are covered as well as click, because select2 commits a choice
            // on the mouse events rather than on the synthesised click, and
            // stopping only the click would still have selected the row.
            $rendered.find('a').on('mousedown mouseup click', function (event) {
                event.stopPropagation();
            });

            return $rendered;
        };

        var data = function (args)
        {
            var lastTerm = $(field).attr("last-search");
            $(this).removeAttr("last-search");

            term = $('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val();
            return {term: lastTerm || term || args.term, page: page};
        }

        function orderFn(selectedEntries) {

            // Pre-order based on selected data
            var ul = $(el.nextElementSibling).find("ul.select2-selection__rendered");
            var search = ul.children(".select2-search");
                search.detach();
            var li = ul.children(".select2-selection__choice");
                li.detach();

            var data = $(field).select2("data");
            var unsorted = $(field).select2("data");

            var output = [];
            selectedEntries.forEach(function (id) {

                var index = data.findIndex(element => element.id == id);
                ul.append(li[index]);
                output.push(id);
                unsorted[index] = null;
            });

            unsorted.filter(x => x != null).forEach(function(u){

                var index = data.findIndex(element => element.id == u.id);
                ul.append(li[index]);
                output.push(u.id);
            });

            if(search)
                ul.append(search);

            return output;
        }

        var processResults = function(data) {

            page = data["pagination"]["page"] || page;
            if(select2["multivalue"]) {

                var results = [];
                var selected = $(field).val()
                var selectedOccurences = selected.reduce(function (acc, curr) {
                    return acc[curr] ? ++acc[curr] : acc[curr] = 1, acc
                }, {});

                $(data.results).each(function() {

                    var id = this.id;
                    var entry = data.results.filter(e => e.id == id)[0];

                    var occurence = parseInt(selectedOccurences[id]);
                    if(occurence) {

                        var limit = Number.isInteger(select2["multivalue"]) ? occurence < select2["multivalue"] : select2["multivalue"];
                        var ext   = (occurence > 0 && limit  ? "/" + (occurence+1) : "");
                        entry.id  = this.id + ext;
                    }

                    results.push(entry);
                });

                data.results = results;
            }

            var flattenResults = [];
            var flattenResultsFn = function() {

                if(Array.isArray(this)) $(this).each(flattenResultsFn);
                else flattenResults.push(this);
            }

            $(data.results).each(flattenResultsFn);
            data.results = flattenResults;

            return data;
        }

        var select2 = JSON.parse(el.getAttribute("data-select2-options")) || {};
            select2["template"]          = "template"          in select2 ? Function('return ' + select2["template"]         )() : defaultTemplate;
            select2["templateResult"]    = "templateResult"    in select2 ? Function('return ' + select2["templateResult"]   )() : defaultTemplate;
            select2["templateSelection"] = "templateSelection" in select2 ? Function('return ' + select2["templateSelection"])() : defaultTemplate;

        if(!(el.getAttribute("data-select2-field") in localCache))
            localCache[el.getAttribute("data-select2-field")] = {};

        if(el.getAttribute("data-select2-field") in localCacheSelected)
            select2["selected"] = localCacheSelected[el.getAttribute("data-select2-field")];
        if(el.getAttribute("data-select2-field") in localCacheData)
            select2["data"] = localCacheData[el.getAttribute("data-select2-field")];

        if("ajax" in select2) {

            select2["ajax"]["data"] = "data" in select2["ajax"] ? Function('return ' + select2["ajax"]["data"])() : data;
            select2["ajax"]["processResults"] = "processResults" in select2["ajax"] ? Function('return ' + select2["ajax"]["processResults"])() : processResults;

            //
            // Debounce option (instead of delay..)
            var firstCall = true;
            var typingDelay = select2["ajax"]["delay"] || 0;
            var debounceFn = true;

            select2["ajax"]["delay"] = 0;
            select2["ajax"]["transport"] = function (options, success, failure) {

                term = options.data.term || '';
                page = options.data.page || '';

                var search = $(field).attr("last-search") || "";
                var typing = term != search;
                if (typing) page = "1.0";

                if(options.data.term == $('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val()) {

                    //
                    // Retrieve cache if exists
                    var index = field.attr("id")+":"+term+":"+page;

                    if(options.cache && index in localCache[el.getAttribute("data-select2-field")])
                        return success(localCache[el.getAttribute("data-select2-field")][index]);

                } else {

                    // Prevent loosing last search..
                    $(field).attr("last-search", $('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val());
                }

                //
                // Compute debouncing (to avoid frequent requests)
                options["delay"] = (firstCall || !typing ? 1 : typingDelay);

                function debounce(t, fn) {

                    if(typeof(debounceFn) != "undefined") clearTimeout(debounceFn);
                    debounceFn = setTimeout(fn, t);
                }

                return debounce(options["delay"], function() {

                    //
                    // Default call with ajax request
                    firstCall = false;

                    //
                    // Retrieve cache if exists
                    var index = field.attr("id")+":"+term+":"+page;
                    options.data.term = term;
                    options.data.page = page;

                    if(options.cache && index in localCache[el.getAttribute("data-select2-field")])
                        return success(localCache[el.getAttribute("data-select2-field")][index]);

                    return $.ajax(options)
                                .done((_response) => localCache[el.getAttribute("data-select2-field")][index] = _response)
                                .done(success)
                                .fail(function(_response) 
                                {        
                                    var msg = "Unexpected response received.";            
                                    if(_response) msg = _response.responseJSON;

                                    $('body > .select2-container .loading-results .select2-selection__entry')
                                        .html("<span style='color:red;'>"+msg+"</span>");

                                    delete localCache[el.getAttribute("data-select2-field")][index];
                                })
                                .fail(failure);
                });
            }
        }

        //
        // Pre-populated data
        if(select2["data"].length != 0) $(field).empty();
        $(field).val(select2["selected"] || [])[0].dispatchEvent(new Event("change"));

        var thumbnails = $("[id^="+el.getAttribute('id')+"_]");

        //
        // Apply required option
        var parent = parent || $(field).parent();
        $(field).select2(select2).on("select2:unselecting", function(e) {

            $(this).data('state', 'unselected');

        }).on("change", function(e) {

        }).on("select2:select", function(e) {

            if(!select2["multivalue"])
                select2["selected"] = orderFn(select2["selected"]);

            // Remove search field content after hitting enter
            $('body > .select2-container input.select2-search__field').focus().val("");
            $(field).parent().find('input.select2-search__field').val("");

            localCacheSelected[el.getAttribute("data-select2-field")] = select2["selected"];
            localCacheData[el.getAttribute("data-select2-field")] = select2["data"];

            $(thumbnails).removeClass("selected");
            if($(field).val() <= thumbnails.length && $(field).val() > 0)
                $(thumbnails[$(field).val()-1]).addClass("selected");

        }).on("select2:unselect", function(e) {

            if(!select2["multivalue"])
                select2["selected"] = orderFn(select2["selected"]);

            localCacheSelected[el.getAttribute("data-select2-field")] = select2["selected"];
            localCacheData[el.getAttribute("data-select2-field")] = select2["data"];

            if($(field).val() <= thumbnails.length && $(field).val() > 0)
                $(thumbnails[$(field).val()-1]).removeClass("selected");

        }).on("select2:open", function(e) {

            // Put back previous value
            $('body > .select2-container input.select2-search__field').focus().val($(this).attr("last-search"));
            $(field).parent().find('input.select2-search__field').val($(this).attr("last-search"));

            page = "1.0";
            if ($(this).data('state') === 'unselected') {
                $(this).removeData('state');
                $(this).select2('close');
            }

        }).on("select2:close", function(e) {

            this.dispatchEvent(new Event("focusout"));
            $(document).off("keyup.select2");

            page = "1.0";

            localCacheSelected[el.getAttribute("data-select2-field")] = select2["selected"];
            localCacheData[el.getAttribute("data-select2-field")] = select2["data"];

        }).on("select2:closing", function(e) {

            $(this).attr("last-search", $('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val());
        });

        // select2 clamps the result list itself: as soon as the wheel would
        // carry past the first or the last option, Results.prototype.bind's
        // `mousewheel` handler preventDefault()s the event and re-assigns
        // scrollTop (select2.js:1260). That fires on EVERY wheel event at
        // either end, so it fights the browser's own elastic overscroll -
        // the list jitters while scrolling and then sits frozen once it is
        // at the bottom, until the wheel is turned the other way. The only
        // thing the handler buys is keeping the wheel from reaching the page
        // behind, and `overscroll-behavior: contain` (form-type-select2.css)
        // now does that natively, so the handler is dropped. Unbound per
        // instance rather than by deleting $.fn.mousewheel, which is a
        // global on a jQuery shared with everything else on the page.
        var instance = $(field).data('select2');
        if (instance && instance.results && instance.results.$results)
            instance.results.$results.off('mousewheel');

        $('body > .select2-container input.select2-search__field').off();
        $('body > .select2-container input.select2-search__field').on("input", function() { page = "1.0"; });

        $(field).parent().find('input.select2-search__field').off();
        $(field).parent().find('input.select2-search__field').on("input", function() { page = "1.0"; });

        var sortable = el.getAttribute("data-select2-sortable") || false;
        if(!select2["multivalue"] && sortable) {

            // Initialize sorting feature. containment/start/update are real
            // jQuery UI Sortable options (this is .sortable(), not
            // SortableJS - ui.item/ui.placeholder and this update()
            // signature are jQuery UI's API); swapThreshold/animation are
            // SortableJS-only option names that jQuery UI silently ignores,
            // apparently copy-pasted from SortableJS's own docs - dropped
            // rather than left in as dead config that reads as tuning
            // something it never touched.
            var choices = $(el.nextElementSibling).find("ul.select2-selection__rendered");
                choices.sortable({
                    containment: 'parent',
                    start: function(e, ui){
                        ui.placeholder.height(ui.item.height());
                    },
                    update: function() {

                        var selectElement = $("#"+el.getAttribute("data-select2-field"));
                        var orderBy = selectElement.parent().find("ul.select2-selection__rendered").children("li[title]").map(function(i, obj){
                            return this.getAttribute("title");
                        });

                        orderBy.each(i => {
                            const item = Array.from(selectElement.find("option")).find(x => x.innerText === orderBy[i]);
                            if (item) item.parentElement.appendChild(item);
                        });
                    },
                });

            select2["selected"] = orderFn(select2["selected"]);
        }


        //
        // Handle thumbnail selection
        if(thumbnails.length) {

            var options = $(field).find("option");
            var thumbnailIds = [];

            if (typeof ($(field).val()) == "object") { // multiples

                var values = $(field).val();
                $(values).each(function (i) {

                    var option = $(field).find("option[value=" + this + "]")[0];
                    thumbnailIds.push(options.index(option));
                });

            } else {

                var option = $(field).find("option[value=" + $(field).val() + "]")[0];
                thumbnailIds.push(options.index(option));
            }

            $(thumbnailIds).each(function () {

                if (this <= thumbnails.length)
                    $(thumbnails[this]).addClass("selected");
            });

            $(thumbnails).each(function () {

                $(this).off("click.select2-thumbnail");
                $(this).on("click.select2-thumbnail", function () {

                    $(thumbnails).removeClass("selected");
                    $(this).addClass("selected");
                    if ($(this).hasClass("selected")) {

                        var i = $(this).attr("id").substring(el.getAttribute('id').length + 1);
                        var option = $("#" + el.getAttribute('id')).find("option")[parseInt(i)];
                        $(field).val(parseInt(option.value))[0].dispatchEvent(new Event("change"));
                    }
                });
            });
        }

    }));
});

// Restoring a previous value into a select2 field (see form-type-history.js).
//
// select2 renders from the underlying <select>'s options, and an ajax-backed
// select2 only ever has options for what is currently selected - so setting
// .value to an id that was removed from the list would select nothing. The
// stored value carries {id, label} for exactly this reason: the options are
// rebuilt from it, which also means a restored tag shows its name rather than
// a bare number.
window.addEventListener("restore.form_type", function (event) {

    var id = event.detail && event.detail.id;
    if (!id) return;

    // event.detail.target is the resolved CONTROL, not the form node - an
    // association's <select> is "<id>_choice" and there is nothing at "<id>".
    var field = event.detail.target || document.getElementById(id);
    if (!field || field.tagName !== "SELECT" || !$(field).data("select2")) return;

    var value = event.detail.value;
    var entries = Array.isArray(value) ? value : (value === null || value === undefined ? [] : [value]);

    $(field).empty();
    entries.forEach(function (entry) {

        var optionId = (entry && entry.id !== undefined) ? entry.id : entry;
        var label = (entry && entry.label) ? entry.label : String(optionId);
        if (optionId === null || optionId === undefined) return;

        $(field).append(new Option(label, optionId, true, true));
    });

    $(field).trigger("change");
    event.preventDefault();
});
