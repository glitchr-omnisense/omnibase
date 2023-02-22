//
// Apply form validation
window.addEventListener('load', function(event) {

    dispatchEvent(new Event("load.form_type"));
    dispatchEvent(new Event("load.collection_type"));
    dispatchEvent(new Event("load.array_type"));
});

window.addEventListener('load', function(event) {

    $("form.needs-validation input").on("invalid", (e) => e.preventDefault() );

    $(window).keydown(function(event){

        if(event.keyCode == 13) {

            var target = $(event.target);
            var form = target.closest("form");

            var submitter = undefined;
            while(target.parent().length) {

                submitter = $(target).find("button[type=submit]");
                if(submitter.length) break;

                target = target.parent();
            }

            if(submitter.length) {

                event.preventDefault();
                submitter.trigger("click");

                return false;
            }
        }
    });

    $("form").on("submit", function(e) {

        // Disable form
        if (this.getAttribute("disabled") != null) return e.preventDefault();

        // Disable submitter to avoid double submission..
        var submitter = e.originalEvent.submitter || undefined;
        if (submitter) {

            $(submitter).addClass('disabled');
            $(".tooltip").remove();
            $(".popover").remove();
        }

        if ( $(this).hasClass("needs-validation") ) {

            if (!this.checkValidity()) {

                e.preventDefault();
                e.stopPropagation();

                if (submitter != undefined)
                    $(submitter).removeClass("disabled").removeAttr("disabled");
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

