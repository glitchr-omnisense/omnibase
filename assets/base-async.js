import '@glitchr/lightbox2';
import '@glitchr/cookie-consent';
import '@glitchr/clipboardjs';

import '@glitchr/imaginejs';

import 'bootstrap-icons/font/bootstrap-icons';

import { Tooltip, Modal, Popover } from 'bootstrap';
$(window).on("load.tooltip", function() { $('[data-toggle="tooltip"]').tooltip({html:true}) });
$(window).on("onbeforeunload.tooltip",function() { $("div[id^='tooltip']").hide().remove(); });
$(window).on("load.popover", function() { $('[data-toggle="popover"]').popover({html:true}) });
$(window).on("onbeforeunload.popover",function() { $("div[id^='popover']").hide().remove(); });

import '@fortawesome/fontawesome-free/css/all.min.css';
import fontawesome from '@fortawesome/fontawesome-free'
fontawesome.config = { autoReplaceSvg: false }

import './styles/base-async.scss';

import './styles/js/user.js';
import './styles/js/flashbag.js';
import './styles/js/countdown.js';