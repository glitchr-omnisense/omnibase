
import Tab from 'bootstrap/js/dist/tab';

window.addEventListener("load.form_type", function () {

    const isEmpty = (value) => value == undefined || !value.trim().length;
    var submitButtons = document.querySelectorAll('button[type="submit"]');

    var forms = $(document.querySelectorAll("form .form-translatable .nav-tabs")).closest("form");
        forms.each(function (e) {

            var form = this;
            var formButtons = this.querySelectorAll('button[type="submit"]');

            var buttons = new Set();
            $(formButtons).each(function() { buttons.add(this); });
            $(submitButtons).each(function() { if(this.form == form) buttons.add(this); });
            buttons = Array.from(buttons);

            var navTabs = $(form).find(".nav-tabs");
            var requiredLocales = [];
            var optionalLocales = [];

            $(navTabs).find("button").each(function() {
                if( $(this).find("label").hasClass("required") ) requiredLocales.push(this.getAttribute("aria-controls"));
                else optionalLocales.push(this.getAttribute("aria-controls"));
            });

            var allLocales = requiredLocales.concat(optionalLocales);

            var submitFn = function (event) {

                if($(this).data("xcheck")) return;

                var requiredFields  = {};
                var invalidRequired = {};
                var allEmptyFields  = {};

                $(optionalLocales).each(function() {

                    var locale = this;
                    $(navTabs).each(function() {

                        var formTranslations = $(this).closest(".form-translatable");
                        var id = formTranslations.attr("id") + "_" + locale;

                        allEmptyFields[locale] = true;
                        $('[id^="'+id+'_"]').each(function() { return allEmptyFields[locale] = allEmptyFields[locale] && isEmpty(this.value); });

                        var tabWarning = $("#"+id+"-tab").find("span");
                        var invalidRequiredField = $('[id^="'+id+'_"]:required:invalid');
                        if(invalidRequiredField.length) {

                            invalidRequired[locale] = invalidRequiredField;
                            if(allEmptyFields[locale]) {

                                requiredFields[locale] = $('[id^="'+id+'_"][required]');
                                requiredFields[locale].removeAttr("required");
                                requiredFields[locale].parent().removeClass("has-error");
                            }

                            var nWarnings = invalidRequiredField.find("[required][invalid]").length;
                            if (nWarnings && tabWarning.hasClass("badge badge-danger")) $(tabWarning).html(nWarnings);
                            else $(tabWarning).remove();
                        }
                    });
                });

                var formValidity = form.checkValidity();
                if (formValidity || allLocales.length == 0) {
                    $(this).data("xcheck", true);
                    return $(this).click();
                }

                var focusTriggered = false;
                $(allLocales).each(function() {

                    var locale = this;
                    $(navTabs).each(function(k) {

                        var formTranslations = $(this).closest(".form-translatable");
                        var id = formTranslations.attr("id") + "_" + locale;

                        var invalidRequiredField = $('[id^="'+id+'_"]:required:invalid');
                        if (invalidRequiredField.length) {

                            if(locale in optionalLocales && allEmptyFields[locale]) return;

                            var tabPane = $(invalidRequiredField.closest(".tab-pane"));
                            var navItem = navTabs.children()[tabPane.index()];

                            if(!focusTriggered) {

                                var navButton = $(navItem).find("button");

                                if (navButton.hasClass("active")) invalidRequiredField[0].reportValidity();
                                else {

                                    navButton.one('shown.bs.tab', function() {
                                        invalidRequiredField[0].reportValidity();
                                    }).tab("show");
                                }
                            }

                            focusTriggered = true;
                        }
                    });
                });

                $(optionalLocales).each(function() {
                    // Restore state
                    $(Object.keys(requiredFields)).each(function() {
                        var locale = this;
                        requiredFields[locale].attr("required", "required");
                    });
                });

                if(!focusTriggered)
                    form.reportValidity();

                return false;
            };

            if(allLocales.length > 0) {
                $(buttons).off("click.translatable.submit");
                $(buttons).on("click.translatable.submit", submitFn);
            }
        });
});