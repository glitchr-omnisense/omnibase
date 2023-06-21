//
// Apply form validation
window.addEventListener('load', function(event) {

    dispatchEvent(new Event("load.form_type"));
    dispatchEvent(new Event("load.collection_type"));
    dispatchEvent(new Event("load.array_type"));
});

$.fn.find_siblings = function (e = "") {
    return this.length ? $(this[0].parentNode).children(e).not(this[0]) : [];
};
$.fn.find_in_siblings = function (e = "") {
    return this.length ? $(this[0].parentNode).find(e).not(this[0]) : [];
};

window.addEventListener('load', function(event) {

    $("form :input").keydown(function(event){
        if(event.keyCode == 13) {

            var form = $(this).closest("form");
            if(form.length) {

                if ($(this).find_siblings("[type=submit]").length == 1) {
                    $(this).find_siblings("[type=submit]").trigger("click");
                } else if ($(this).find_siblings("[type=button]").length == 1) {
                           $(this).find_siblings("[type=button]").trigger("click");
                } else if ($(this).find_in_siblings("[type=submit]").length == 1) {
                           $(this).find_in_siblings("[type=submit]").trigger("click");
                } else if ($(this).find_in_siblings("[type=button]").length == 1) {
                           $(this).find_in_siblings("[type=button]").trigger("click");
                } else if ($(this).closest("[type=submit]").length == 1) {
                           $(this).closest("[type=submit]").trigger("click");
                } else if ($(this).closest("[type=button]").length == 1) {
                           $(this).closest("[type=button]").trigger("click");
                } else if(form.find("[type=submit]").length == 1) {
                        form.find("[type=submit]").trigger("click");
                } else if(form.find("[type=button]").length == 1) {
                        form.find("[type=button]").trigger("click");
                } else if(form.find("[type=submit]").length > 1) {
                        return false; // Prevent submission form submission using ENTER
                } else if(form.find("[type=button]").length > 1) {
                        return false; // Prevent submission form submission using ENTER
                } else { 
                    form.trigger("submit");
                }
            }
        }
    });

    $("form").addClass("needs-validation").attr("novalidate", "");
    $("form").on("submit", function(e) {

        // Disable form
        if (this.getAttribute("disabled") != null) return e.preventDefault();

        // Disable submitter to avoid double submission..
        var submitter = e.originalEvent ? e.originalEvent.submitter : undefined;
        if (submitter) {

            $(".tooltip").remove();
            $(".popover").remove();
        }

        if ( $(this).hasClass("needs-validation") && !$(submitter).hasClass("skip-validation")) {

            if (!this.checkValidity()) { 

                e.preventDefault();
                e.stopPropagation();

                var invalid = $(this).find(".form-control:invalid");
                if (invalid.length) {
                    var navPane = $(invalid[0]).closest(".tab-pane");

                    var navButton = $("#"+navPane.attr("aria-labelledby"));
                        navButton.one('shown.bs.tab', function() {
                            invalidRequiredField[0].reportValidity();
                        });

                    var target = navButton.data("bs-target");
                    if (target) location.hash = target;
                }

            }

            var el = $(this).find(":invalid, .has-error");
            if (el.length) {

                // Flag elements as..
                const style = getComputedStyle(document.body);
                $([document.documentElement, document.body]).animate(
                    {scrollTop: $(el[0]).offset().top - parseInt(style["scroll-padding-top"])},
                    function () { $(this).addClass('was-validated'); }.bind(this)
                );
            }
        }
    });
});

