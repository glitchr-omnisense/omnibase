
import '@glitchr/imaginejs';

import Button from 'bootstrap/js/dist/tooltip';

import "./styles/js/button";

import Tooltip from 'bootstrap/js/dist/tooltip';
$(window).on("load.tooltip", function() { $('[data-toggle="tooltip"]').tooltip({html:true}) });
$(window).on("onbeforeunload.tooltip",function() { $("div[id^='tooltip']").hide().remove(); });

import Popover from 'bootstrap/js/dist/popover';
$(window).on("load.popover", function() { $('[data-toggle="popover"]').popover({html:true}) });
$(window).on("onbeforeunload.popover",function() { $("div[id^='popover']").hide().remove(); });

import 'bootstrap-icons/font/bootstrap-icons';

import '@fortawesome/fontawesome-free/css/all.min.css';
import fontawesome from '@fortawesome/fontawesome-free'
fontawesome.config = { autoReplaceSvg: false }
