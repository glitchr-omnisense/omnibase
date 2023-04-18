import './styles/easyadmin-async.scss';

var spinnerTimeout = setTimeout(function() { $(".content").addClass("spinner"); }, 1000);
$(window).on("load", function(e) {

    $(".content").addClass("spinner");
    $(".spinner").addClass("spinner loaded");
    clearTimeout(spinnerTimeout);
});

//
// Apply bootstrap form validation
window.addEventListener('load', function(event) {

    $("form :input").on("change", function () { // Reactivate button when a form is changed
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    $("form :input").on("input", function () { // Reactivate button when a form is changed
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    // Input event is not working sometimes for select2
    const observer = new MutationObserver(() => {
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    $("form select").each(function() {
        observer.observe(this, {subtree: true, childList: true});
    });

});
