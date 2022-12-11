
//
// Apply bootstrap form validation
window.addEventListener('load', function(event) {

    $("form.needs-validation input").on("invalid", (e) => e.preventDefault() );
    $("[type=submit]").on("click", function() {

        const style = getComputedStyle(document.body);

        var form = $(".has-error").closest("form.needs-validation");
        if(!form.length) form = $(this).closest("form.needs-validation");

        if (!this.checkValidity()) {

            event.preventDefault();
            event.stopPropagation();
        }

        var el = $(form).find(":invalid, .has-error");
        if (el.length) {

            return $([document.documentElement, document.body]).animate(
                {scrollTop: $(el[0]).offset().top - parseInt(style["scroll-padding-top"])},
                function() {
                    form.addClass('was-validated');
                }
            );
        }
    });
});