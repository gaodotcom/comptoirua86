(function () {
    'use strict';

    // Bouton [data-copy-target="<id>"] : copie la valeur du champ visé dans le presse-papiers.
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-copy-target]');
        if (!btn) { return; }

        var field = document.getElementById(btn.getAttribute('data-copy-target'));
        if (!field) { return; }

        var label = btn.querySelector('.copy-label');
        var done = function () {
            if (!label) { return; }
            var original = label.textContent;
            label.textContent = 'Copié !';
            setTimeout(function () { label.textContent = original; }, 2000);
        };

        field.select();
        // API moderne (HTTPS), sinon repli sur execCommand.
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(field.value).then(done);
        } else if (document.execCommand('copy')) {
            done();
        }
    });
})();
