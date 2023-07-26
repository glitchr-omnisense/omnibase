import '@glitchr/ajaxer';
import '@glitchr/breakpoints';
import '@glitchr/imaginejs';

import Button from 'bootstrap/js/dist/tooltip';
import "./styles/js/button";

import Popover from 'bootstrap/js/dist/popover';
$(window).off("load.popover");
$(window).on("load.popover", function() { $('[data-toggle="popover"]').popover({html:true}) });
$(window).off("onbeforeunload.popover");
$(window).on("onbeforeunload.popover",function() { $("div[id^='popover']").hide().remove(); });

import Tooltip from 'bootstrap/js/dist/tooltip';
$(window).off("load.tooltip");
$(window).on("load.tooltip", function() { $('[data-toggle="tooltip"]').tooltip({html:true}) });

$(window).off("onbeforeunload.tooltip");
$(window).on("onbeforeunload.tooltip",function() { $("div[id^='tooltip']").hide().remove(); });

$(".copy-clipboard").off("click");
$(".copy-clipboard").on("click", function () {

    var value = "";
    if(this.tagName === "INPUT") value = this.value;
    else if(this.tagName === "BUTTON") value = this.innerText;

    if(value) {

        navigator.clipboard.writeText(value);
        $(this).tooltip("enable");
        $(this).tooltip("show");

        setTimeout(function () {

            $(this).tooltip("hide");
            $(this).tooltip("disable");

        }.bind(this), 300);
    }
});

import 'bootstrap-icons/font/bootstrap-icons';

import '@fortawesome/fontawesome-free/css/all.min.css';
import fontawesome from '@fortawesome/fontawesome-free'
fontawesome.config = { autoReplaceSvg: false }
