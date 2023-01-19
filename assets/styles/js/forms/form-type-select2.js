import '@glitchr/select2';

$(document).on("DOMContentLoaded", function () {

    var localCache = {};
    function is_dict(v) {
        return typeof v==='object' && v!==null && !(v instanceof Array) && !(v instanceof Date);
    }

    function highlight_search(text, search) {

        var reg = new RegExp(search, 'gi');
        return text.replace(reg, function(str) {return '<mark>'+str+'</mark>'});
    }

    var dropdown = [];
    $(document).on("load.form_type.select2", function () {

        document.querySelectorAll("[data-select2-field]").forEach((function (el) {

            var field = $("#"+el.getAttribute("data-select2-field"));
            var defaultTemplate = function(option) {

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

                var term = $('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val();
                option.text = option.text.replace("<mark>", "").replace("</mark>", "");

                if (highlight && term) option.text = highlight_search(option.text, term);
                return $('<span style="margin-left:calc('+tab+' * '+depth+')" class=\"select2-selection__entry\" '+dataAttribute+'><span>' +
                            (option.html ? option.html : (iconAttributes ? '<i '+ iconAttributes + '></i> ' : '') + (option.text) + "</span>" +
                            (href ? '<span><a target="_blank" href="'+href+'"><i class=\"fas fa-external-link-square-alt\"></i></span>' : '') +
                        '</span>'));
            };

            var page = "1.0";
            var data = function (args)
            {
                var lastTerm = $(field).attr("last-search");
                $(this).removeAttr("last-search");

                var term = $('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val();
                return {term: lastTerm || term || args.term, page: page};
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

                    var term = options.data.term || '';
                    var page = options.data.page || '';
                    if(options.data.term == ($('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val())) {

                        //
                        // Retrieve cache if exists
                        var index = field.attr("id")+":"+term+":"+page;

                        if(options.cache && index in localCache)
                            return success(localCache[index]);

                        page = 1;

                    } else {

                        // Prevent loosing last search..
                        $(field).attr("last-search", ($('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val()));
                    }

                    //
                    // Compute debouncing (to avoid frequent requests)
                    options["delay"] = (firstCall ? 0 : typingDelay);

                    function debounce(t, fn) {

                        if(typeof(debounceFn) != "undefined") clearTimeout(debounceFn);
                        debounceFn = setTimeout(fn, t);
                    }

                    return debounce(options["delay"], function() {

                        //
                        // Default call with ajax request
                        dropdown = [];
                        firstCall = false;

                        //
                        // Retrieve cache if exists
                        var index = field.attr("id")+":"+term+":"+page;
                        options.data.term = term;
                        options.data.page = page;

                        if(options.cache && index in localCache)
                            return success(localCache[index]);

                        return $.ajax(options)
                                    .done((_response) => localCache[index] = _response)
                                    .done(success)
                                    .fail(function(_response) 
                                    {                    
                                        var response = JSON.parse(_response.responseText);
                                        $('body > .select2-container .loading-results .select2-selection__entry')
                                            .html("<span style='color:red;'>"+response["status"]+"</span>");

                                        delete localCache[index];
                                    })
                                    .fail(failure);

                            /*[......].done(function(data) {

                            // Select all entries [not working :-(]
                            // $(document).on("keyup.select2", ".select2-search__field", function (e) {

                            //     if (e.keyCode === 65 && e.ctrlKey )
                            //         $(field).val(data.results.map((o) => o.id)).trigger("change");
                            // });
                        });*/
                    });
                }
            }

            //
            // Pre-populated data
            // if(select2["data"].length != 0) $(field).empty();
            $(field).val(select2["selected"] || []).trigger("change");

            //
            // Apply required option
            select2["containerCssClass"] = select2["containerCssClass"] + ($(field).attr('required') ? 'required' : '');

            var parent = parent || $(field).parent();
            $(field).select2(select2).on("select2:unselecting", function(e) {

                $(this).data('state', 'unselected');

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

                $(this).focusout();
                $(document).off("keyup.select2");

                page = "1.0";

            }).on("select2:closing", function(e) {

                $(this).attr("last-search", $('body > .select2-container input.select2-search__field').val() || $(field).parent().find('input.select2-search__field').val());
            });

            $('body > .select2-container input.select2-search__field').off();
            $('body > .select2-container input.select2-search__field').on("input", function() { page = "1.0"; });

            $(field).parent().find('input.select2-search__field').off();
            $(field).parent().find('input.select2-search__field').on("input", function() { page = "1.0"; });

            dropdown = $(field).select2("data");
 
            var sortable = el.getAttribute("data-select2-sortable") || false;
            if(sortable) {

                var choices = $(el.nextElementSibling).find("ul.select2-selection__rendered");
                    choices.sortable({
                        containment: 'parent',
                        swapThreshold: 0.50,
                        animation: 150,
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
            }
        }));
    });

    $(document).trigger("load.form_type.select2");
});