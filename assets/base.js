
// start the Stimulus application

import 'bootstrap';
window.bootstrap = bootstrap;
window.$ = jQuery;

require('bootstrap/dist/css/bootstrap.min.css');
require('bootstrap-icons/font/bootstrap-icons.css');

import '@glitchr/lightbox2';
import '@glitchr/cookie-consent';
import '@glitchr/transparent';

import "@fortawesome/fontawesome-free/css/all.min.css";
import '@fortawesome/fontawesome-free/js/fontawesome'
import '@fortawesome/fontawesome-free/js/solid'
import '@fortawesome/fontawesome-free/js/regular'
import '@fortawesome/fontawesome-free/js/brands'
window.FontAwesomeConfig = { autoReplaceSvg: false }


import './styles/base.scss';

import './styles/js/user.js';
import './styles/js/flashbag.js';
import './styles/js/countdown.js';
import './styles/js/form.js';

// window.addEventListener('load', function(event) {
    
//     lightbox.option({'wrapAround': true});
// });
