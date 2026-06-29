(function () {
    'use strict';

    if (typeof window.EBOH_ML === 'undefined') return;

    var forms = document.querySelectorAll('.eboh-ml-form');
    if (!forms.length) return;

    forms.forEach(function (wrap) {
        var form = wrap.querySelector('.eboh-ml-form__form');
        if (!form) return;

        // Injecteer honeypot-veld (verborgen, bots vullen 't vaak in)
        var hp = document.createElement('input');
        hp.type = 'text';
        hp.name = 'hp';
        hp.tabIndex = -1;
        hp.autocomplete = 'off';
        hp.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;opacity:0;';
        form.appendChild(hp);

        var feedback = form.querySelector('.eboh-ml-form__feedback');
        var submit = form.querySelector('.eboh-ml-form__submit');
        var emailInput = form.querySelector('.eboh-ml-form__email');
        var nonceInput = form.querySelector('input[name="eboh_ml_nonce"]');
        var consentInput = form.querySelector('.eboh-ml-form__consent-input');

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            setFeedback('', '');

            var email = (emailInput.value || '').trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                setFeedback('Vul een geldig e-mailadres in.', 'error');
                emailInput.focus();
                return;
            }
            if (consentInput && !consentInput.checked) {
                setFeedback('Vink eerst de toestemming aan.', 'error');
                consentInput.focus();
                return;
            }

            var data = new FormData();
            data.append('action', window.EBOH_ML.action);
            data.append('nonce', nonceInput ? nonceInput.value : '');
            data.append('email', email);
            data.append('group', wrap.getAttribute('data-group') || '');
            if (consentInput && consentInput.checked) {
                data.append('consent', '1');
            }
            data.append('hp', hp.value);

            submit.disabled = true;

            fetch(window.EBOH_ML.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            })
                .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
                .then(function (res) {
                    submit.disabled = false;
                    if (res.body && res.body.success) {
                        setFeedback(
                            (res.body.data && res.body.data.message) || window.EBOH_ML.success,
                            'success'
                        );
                        wrap.classList.add('is-submitted');
                    } else {
                        setFeedback(
                            (res.body && res.body.data && res.body.data.message) || window.EBOH_ML.error,
                            'error'
                        );
                    }
                })
                .catch(function () {
                    submit.disabled = false;
                    setFeedback(window.EBOH_ML.error, 'error');
                });
        });

        function setFeedback(msg, kind) {
            if (!feedback) return;
            feedback.textContent = msg;
            feedback.classList.remove('eboh-ml-form__feedback--success', 'eboh-ml-form__feedback--error');
            if (kind === 'success') feedback.classList.add('eboh-ml-form__feedback--success');
            if (kind === 'error') feedback.classList.add('eboh-ml-form__feedback--error');
        }
    });
})();
