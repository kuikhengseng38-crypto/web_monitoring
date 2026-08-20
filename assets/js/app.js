document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-toggle-password]');
    if (!button) {
        return;
    }
    var input = document.getElementById(button.getAttribute('data-toggle-password'));
    if (!input) {
        return;
    }
    var hidden = input.type === 'password';
    input.type = hidden ? 'text' : 'password';
    button.textContent = hidden ? 'Hide' : 'Show';
});

document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.matches('[data-confirm]')) {
        return;
    }
    if (!confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

(function () {
    if (!document.body || document.body.getAttribute('data-auto-check') !== '1') {
        return;
    }
    var fingerprintKey = 'monitorFingerprint';
    setInterval(function () {
        fetch('auto_check.php', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data) {
                    return;
                }
                if (data.fingerprint && data.fingerprint !== sessionStorage.getItem(fingerprintKey)) {
                    var previous = sessionStorage.getItem(fingerprintKey);
                    sessionStorage.setItem(fingerprintKey, data.fingerprint);
                    if (previous) {
                        window.location.reload();
                    }
                    return;
                }
                if (data.checked > 0) {
                    window.location.reload();
                }
            })
            .catch(function () {});
    }, 20000);
})();
