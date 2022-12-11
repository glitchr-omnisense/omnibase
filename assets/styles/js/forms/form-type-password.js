$(document).on("DOMContentLoaded", function () {

    $(document).on("load.form_type.password", function () {

        document.querySelectorAll("[data-password-field]").forEach(function (el) {

            var id = el.getAttribute("data-password-field");

            var plainPassword = $("#"+id+"_plain");
            var plainPasswordRepeater = $("#"+id+"_plain_repeater");
            var revealer = $("#"+id+"_revealer");
            var details  = $("#"+id+"_details");

            var strengthLabel    = el.getAttribute("data-password-strength");

            var minStrength      = el.getAttribute("data-password-minstrength") ?? 0;
            var minStrengthStr   = el.getAttribute("data-password-minstrength[feedback]");
            var minLength        = el.getAttribute("data-password-minlength") ?? 0;
            var minLengthStr     = el.getAttribute("data-password-minlength[feedback]");
            var allowEmpty       = el.getAttribute("data-password-allow-empty") || minLength == 0;
            var passwordMatchStr = el.getAttribute("data-password-match[feedback]");

            function checkStrength(strength) {
                plainPassword[0].setCustomValidity(strength >= minStrength ? "" : minStrengthStr);
                return strength >= minStrength;
            }

            function checkLength() {
                plainPassword[0].setCustomValidity(((allowEmpty && plainPassword.val().length == 0 ) || plainPassword.val().length >= minLength) ? "" : minLengthStr);
                return (allowEmpty && plainPassword.val().length == 0 ) || plainPassword.val().length >= minLength;
            }

            function checkMatch() {
                if(!plainPasswordRepeater.length) return "";
                plainPasswordRepeater[0].setCustomValidity(plainPassword.val() != plainPasswordRepeater.val() ? passwordMatchStr : "");
                return plainPassword.val() != plainPasswordRepeater.val() ? passwordMatchStr : "";
            }

            if (checkStrength(0) )
            if (checkLength() )
                checkMatch();

            var strength;
            plainPassword.on('input', function(e) {

                strength = 0;
                strengthList = [/[a-z]+/, /[A-Z]+/,/[0-9]+/,/\W+/,/.{12,}/];

                for (let i = 0; i < strengthList.length; i++) {

                    var ith = details.find("li > i")[i];
                    if(!strengthList[i].test(plainPassword.val())) {

                        $(ith).removeClass("fa-check-circle check").addClass("fa-times-circle uncheck");

                    } else {

                        $(ith).removeClass("fa-times-circle uncheck").addClass("fa-check-circle check")
                        strength++;
                    }
                }

                $('[id^="'+id+'_strength_"]').removeClass().addClass("strength");
                for (let i = 1; i < strength+1; i++)
                    $("#"+id+"_strength_"+i).removeClass().addClass("strength strength_"+strength);

                if(plainPassword.val().length) {

                    $("#"+id+"_details p.strength-label").html(
                        strengthLabel.replaceAll("{force}", strength)
                                     .replaceAll("{label}", el.getAttribute("data-password-strength-name["+(strength-1)+"]"))
                        );
                    $("#"+id+"_details p.strength-message").html(el.getAttribute("data-password-strength-message["+(strength-1)+"]"));

                } else {

                    $("#"+id+"_details p.strength-label").html("<br/>");
                    $("#"+id+"_details p.strength-message").html("<br/>");
                }

                if (checkStrength(strength) )
                if (checkLength() )
                    checkMatch();
            });

            if (plainPasswordRepeater.length) {
                plainPasswordRepeater.on('input', checkMatch);
                checkMatch();
            }

            revealer.on("click", function (e) {

                var isRevealed = plainPassword.prop("type") === 'text';

                revealer.removeClass("fa-eye fa-eye-slash").addClass(isRevealed ? "fa-eye" : "fa-eye-slash");
                plainPassword.prop('type', isRevealed ? 'password' : 'text');
            });
        });
    });

    $(document).trigger("load.form_type.password");
});