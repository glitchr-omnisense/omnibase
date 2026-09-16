import $ from 'jquery';
window.jQuery = $;

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

    $("form :input").on("keydown", function(event){
        
        if(event.key === 'Enter') {

            var form = $(this).closest("form");
            if(form.length) {

                var button = undefined;
                if ($(this).find_siblings("[type=submit]").length == 1) {
                    button = $(this).find_siblings("[type=submit]");
                } else if ($(this).find_siblings("[type=button]").length == 1) {
                    button = $(this).find_siblings("[type=button]");
                } else if ($(this).find_in_siblings("[type=submit]").length == 1) {
                    button = $(this).find_in_siblings("[type=submit]");
                } else if ($(this).find_in_siblings("[type=button]").length == 1) {
                    button = $(this).find_in_siblings("[type=button]");
                } else if ($(this).closest("[type=submit]").length == 1) {
                    button = $(this).closest("[type=submit]");
                } else if ($(this).closest("[type=button]").length == 1) {
                    button = $(this).closest("[type=button]");
                } else if(form.find("[type=submit]").length == 1) {
                    button = form.find("[type=submit]");
                } else if(form.find("[type=button]").length == 1) {
                    button = form.find("[type=button]");
                } else if(form.find("[type=submit]").length > 1) {
                    return false; // Prevent submission form submission due to ambiguity
                } else if(form.find("[type=button]").length > 1) {
                    return false; // Prevent submission form submission due to ambiguity
                }

                if(button != undefined) {

                    var isDisabled = button.prop("disabled");
                    if(!isDisabled) button.trigger("click");
                    return false; // Disable by default to prevent double submission, if a button is clicked ..
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
                            // `invalid`, not `invalidRequiredField`: that
                            // name is not defined in this scope, so the
                            // moment this callback ran it threw a
                            // ReferenceError and the field was never
                            // reported.
                            invalid[0].reportValidity();
                        });

                    var target = navButton.data("bs-target");
                    if (target) location.hash = target;
                }

            }

            var el = $(this).find(":invalid, .has-error");
            if (el.length) {

                // Flag elements as..
                $(this).addClass('was-validated');

                // parseFloat and a fallback, and the SCROLLING element rather
                // than the body: scroll-padding-top is "auto" unless a page
                // sets it, parseInt("auto") is NaN, and `offset().top - NaN`
                // is NaN. jQuery animated scrollTop towards NaN, which the
                // browser reads as 0, so every form the browser refused sent
                // the page to its very top - taking the field being complained
                // about off the screen with it. Reported on a site whose
                // header is a full screen of sky: "the form goes back up to
                // the sky and says nothing".
                var scroller = document.scrollingElement || document.documentElement;
                var padding = parseFloat(getComputedStyle(scroller).scrollPaddingTop) || 0;
                var top = Math.max(0, $(el[0]).offset().top - padding);

                // The reason, in the browser's own words. Every form here
                // carries `novalidate` (just above), so the browser will not
                // say anything by itself, and a refusal no one can see reads
                // as a button that does nothing - which is exactly how it was
                // reported. On the control: a form with `novalidate` answers
                // form.reportValidity() with nothing on WebKit.
                var say = function () { try { el[0].reportValidity(); } catch (e) {} };

                // Only when the field is not already on screen: the bubble is
                // dismissed by any scrolling, so a scroll that was not needed
                // takes the message away with it. And after the scroll, never
                // before, for the same reason.
                var box = el[0].getBoundingClientRect();
                var height = window.innerHeight || document.documentElement.clientHeight;

                if (box.top >= 0 && box.bottom <= height) say();
                else $([document.documentElement, document.body]).animate({scrollTop: top}).promise().done(say);
            }
        }
    });
});

