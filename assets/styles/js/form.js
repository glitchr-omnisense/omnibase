//
// Apply form validation
window.addEventListener('load', function(event) {

    dispatchEvent(new Event("load.form_type"));
    dispatchEvent(new Event("load.collection_type"));
    dispatchEvent(new Event("load.array_type"));
});

window.addEventListener('load', function(event) {

    // $("form :input").keydown(function(event){
    //
    //     if(event.keyCode == 13) {
    //
    //         if(event.target.tagName == "TEXTAREA")
    //             return true;
    //
    //         var target = $(event.target);
    //
    //         var submitter = undefined;
    //         while(target.parent().length) {
    //
    //             submitter = $(target).find("button");
    //             if(submitter.length) break;
    //
    //             target = target.parent();
    //
    //             if(target.length && target[0].tagName == "FORM") {
    //
    //                 target.submit();
    //                 break;
    //             }
    //         }
    //
    //         if(submitter != undefined && submitter.length) {
    //
    //             event.preventDefault();
    //             event.stopPropagation();
    //
    //             submitter.trigger("click");
    //             return false;
    //         }
    //     }
    // });

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

                    location.hash = navButton.data("bs-target");
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

