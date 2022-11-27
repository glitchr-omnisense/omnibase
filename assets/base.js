
import 'bootstrap';
$(window).on("onbeforeunload.popover",function() { $("div[id^='popover']").hide().remove(); });
$(window).on("load.tooltip", function() { $('[data-toggle="tooltip"]').tooltip({html:true}) });
$(window).on("onbeforeunload.tooltip",function() { $("div[id^='tooltip']").hide().remove(); });
$(window).on("load.popover", function() { $('[data-toggle="popover"]').popover({html:true}) });

import '@glitchr/lightbox2';
import '@glitchr/cookie-consent';
import '@glitchr/clipboardjs';

import '@fortawesome/fontawesome-free/css/all.min.css';
import fontawesome from '@fortawesome/fontawesome-free'
fontawesome.config = { autoReplaceSvg: false }

import './styles/base.scss';

import './styles/js/user.js';
import './styles/js/flashbag.js';
import './styles/js/countdown.js';
import './styles/js/form.js';
