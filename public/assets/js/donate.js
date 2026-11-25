/* Donate page: custom amount and anonymous giving. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form[data-imara-form="donation"]');
        if (!form) return;
        var customWrap = document.getElementById('custom-amount-wrap');
        var customInput = document.getElementById('amount_custom');
        var anon = document.getElementById('d-anon');
        var nameInput = document.getElementById('d-name');

        function syncAmount() {
            var sel = form.querySelector('input[name="amount"]:checked');
            var custom = sel && sel.value === 'custom';
            customWrap.hidden = !custom;
            customInput.required = custom;
            if (custom) customInput.focus();
        }
        function syncAnon() {
            nameInput.required = !anon.checked;
            nameInput.closest('.field').querySelector('.req').hidden = anon.checked;
            if (anon.checked) nameInput.closest('.field').classList.remove('is-invalid');
        }
        form.addEventListener('change', function (e) {
            if (e.target.name === 'amount') syncAmount();
            if (e.target === anon) syncAnon();
        });
        syncAmount();
        syncAnon();
    });
})();
