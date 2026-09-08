/*
 * One box per character (Base\Field\Type\CodeType). The boxes feed the
 * wrapper's hidden input, which is the real field value: typing moves to
 * the next box, backspace to the previous, a paste of the whole code
 * spreads over the boxes, and data-code-submit submits the form once
 * every box holds a character. Idempotent per wrapper: load.form_type is
 * re-dispatched on every lazy load and this scan is unscoped.
 */
(function () {
    function alphabetRegex(alphabet) {
        if (alphabet === 'digits') return /[^0-9]/g;
        if (alphabet === 'alpha') return /[^a-zA-Z]/g;
        return /[^a-zA-Z0-9]/g;
    }

    function setup(box) {
        if (box.dataset.codeInitialized) return;
        box.dataset.codeInitialized = '1';

        var chars = Array.prototype.slice.call(box.querySelectorAll('.code-char'));
        var hidden = box.querySelector('input[type="hidden"]');
        var n = chars.length;
        if (!n || !hidden) return;
        var strip = alphabetRegex(box.dataset.codeAlphabet);
        var upper = box.dataset.codeUppercase !== '0';
        var clean = function (v) { v = String(v || '').replace(strip, ''); return upper ? v.toUpperCase() : v; };

        function sync() {
            var value = chars.map(function (c) { return c.value; }).join('');
            hidden.value = value;
            chars.forEach(function (c) { c.classList.toggle('is-filled', c.value !== ''); });
            var complete = value.length === n;
            box.classList.toggle('is-complete', complete);
            box.classList.remove('is-error');
            if (complete && box.dataset.codeSubmit === '1' && !box.dataset.codeSubmitted) {
                box.dataset.codeSubmitted = '1';
                var form = box.closest('form');
                if (form) { if (form.requestSubmit) form.requestSubmit(); else form.submit(); }
            }
            box.dispatchEvent(new CustomEvent('code:change', { bubbles: true, detail: { value: value, complete: complete } }));
        }

        // Spread a string over the boxes starting at index `from`.
        function fill(from, text) {
            var v = clean(text);
            for (var k = 0; k < v.length && from + k < n; k++) chars[from + k].value = v[k];
            var last = Math.min(from + v.length, n) - 1;
            if (last >= 0) (last < n - 1 && v.length ? chars[last + 1] : chars[last]).focus();
            sync();
        }

        // Initial value (an edit form, a re-rendered submit) lands in the boxes.
        if (hidden.value) fill(0, hidden.value);

        chars.forEach(function (input, i) {
            input.addEventListener('input', function () {
                var v = clean(input.value);
                if (v.length > 1) { input.value = ''; fill(i, v); return; }
                input.value = v;
                if (v && i < n - 1) chars[i + 1].focus();
                sync();
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !input.value && i > 0) { chars[i - 1].value = ''; chars[i - 1].focus(); sync(); e.preventDefault(); }
                else if (e.key === 'ArrowLeft' && i > 0) { chars[i - 1].focus(); e.preventDefault(); }
                else if (e.key === 'ArrowRight' && i < n - 1) { chars[i + 1].focus(); e.preventDefault(); }
            });
            input.addEventListener('paste', function (e) {
                var data = (e.clipboardData || window.clipboardData);
                var text = data ? data.getData('text') : '';
                if (!clean(text)) return;
                e.preventDefault();
                fill(i, text);
            });
            input.addEventListener('focus', function () { input.select(); });
        });

        // "Type it as one field" (a backup code of another length): swap the
        // boxes for a plain input bound to the same hidden value.
        var manual = box.querySelector('[data-code-manual]');
        if (manual) {
            manual.addEventListener('click', function (e) {
                e.preventDefault();
                var plain = document.createElement('input');
                plain.type = 'text'; plain.className = 'form-control code-manual-input'; plain.autocomplete = 'one-time-code';
                plain.value = hidden.value;
                plain.addEventListener('input', function () { hidden.value = plain.value.trim(); });
                chars.forEach(function (c) { c.remove(); });
                manual.replaceWith(plain);
                plain.focus();
            });
        }
    }

    function scan() { document.querySelectorAll('[data-code-field]').forEach(setup); }
    window.addEventListener('load.form_type', scan);
    window.addEventListener('load', scan);
    if (document.readyState !== 'loading') scan(); else document.addEventListener('DOMContentLoaded', scan);
})();
