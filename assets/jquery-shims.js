// Compatibility shims for jQuery 4 + Bootstrap 5.
// jQuery 4 removed $.isArray; Bootstrap 5 dropped its jQuery plugin layer.
// These shims let legacy form widgets (select2, bootstrap-based modals) keep working.

import jQuery from 'jquery';
import Modal from 'bootstrap/js/dist/modal';


if (!jQuery.isArray) jQuery.isArray = Array.isArray;
if (!jQuery.isFunction) jQuery.isFunction = function (v) { return typeof v === 'function'; };
// jQuery 4 removed $.trim; restore for legacy plugins (e.g., select2)
if (!jQuery.trim) jQuery.trim = function (text) {
    return text == null ? '' : String(text).trim();
};

if (!jQuery.fn.modal) {
    jQuery.fn.modal = function (action) {
        return this.each(function () {
            var options = (typeof action === 'object' && action !== null) ? action : undefined;
            var instance = Modal.getOrCreateInstance(this, options);
            if (typeof action === 'string' && typeof instance[action] === 'function') {
                instance[action]();
            }
        });
    };
}
