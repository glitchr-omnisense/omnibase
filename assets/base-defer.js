import '@glitchr/ajaxer';
import '@glitchr/breakpoints';
import '@glitchr/imaginejs';

import Button from 'bootstrap/js/dist/tooltip';
import "./styles/js/button";

import Popover from 'bootstrap/js/dist/popover';
$(window).off("load.popover");
$(window).on("load.popover", function() { $('[data-toggle="popover"]').each(function() { $(this).popover({"html": $(this).data("html") ?? false}); }); });
$(window).off("onbeforeunload.popover");
$(window).on("onbeforeunload.popover",function() { $('[data-toggle="popover"]').popover("hide"); });

import Tooltip from 'bootstrap/js/dist/tooltip';

// Bootstrap's defineJQueryPlugin() registers $.fn.* only on window.jQuery, which
// may be a different jQuery instance than our ProvidePlugin-injected $ (each
// disableSingleRuntimeChunk entry has its own module registry). Re-register on
// the local instance so $(el).popover() / $(el).tooltip() work in this bundle.
$.fn.popover = $.fn.popover || Popover.jQueryInterface;
$.fn.tooltip = $.fn.tooltip || Tooltip.jQueryInterface;

$(window).off("load.tooltip");
$(window).on("load.tooltip", function() { 
    $('[data-toggle="tooltip"]').each(function() { 
        $(this).tooltip({"html": $(this).data("html") ?? false}); 
        $(this).click(function() { $(this).tooltip("hide"); });
    }); 
});

$(window).off("onbeforeunload.tooltip");
$(window).on("onbeforeunload.tooltip",function() { $('[data-toggle="tooltip"]').tooltip("hide"); });

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
