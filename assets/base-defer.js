import '@glitchr/ajaxer';
import '@glitchr/breakpoints';

import '@glitchr/imaginejs';

import Button from 'bootstrap/js/dist/tooltip';
import "./styles/js/button";

import Tooltip from 'bootstrap/js/dist/tooltip';
$(window).on("load.tooltip", function() { $('[data-toggle="tooltip"]').tooltip({html:true}) });
$(window).on("onbeforeunload.tooltip",function() { $("div[id^='tooltip']").hide().remove(); });

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

import Popover from 'bootstrap/js/dist/popover';
$(window).on("load.popover", function() { $('[data-toggle="popover"]').popover({html:true}) });
$(window).on("onbeforeunload.popover",function() { $("div[id^='popover']").hide().remove(); });

import 'bootstrap-icons/font/bootstrap-icons';

import '@fortawesome/fontawesome-free/css/all.min.css';
import fontawesome from '@fortawesome/fontawesome-free'
fontawesome.config = { autoReplaceSvg: false }
