(function () {
    'use strict';

    var form = document.getElementById('send-form');
    var select = document.getElementById('email');
    var label = document.getElementById('send-label');
    var fields = document.getElementById('excluded-fields');
    var frame = document.getElementById('newsletter-preview');

    if (!form || !select || !label || !fields || !frame) { return; }

    // Le bouton d'envoi indique le destinataire choisi.
    function refresh() { label.textContent = 'Envoyer à ' + select.value; }
    select.addEventListener('change', refresh);
    refresh();

    // Cases de l'aperçu : décocher un élément l'exclut de l'envoi (et grise le bloc s'il est vide).
    function syncChecks(doc) {
        fields.innerHTML = '';
        doc.querySelectorAll('[data-zone]').forEach(function (zone) {
            var boxes = zone.querySelectorAll('.nl-chk');
            var anyChecked = false;
            boxes.forEach(function (box) {
                var item = box.closest('[data-item]');
                if (item) { item.style.opacity = box.checked ? '' : '.3'; }
                if (box.checked) { anyChecked = true; return; }
                var input = document.createElement('input');
                input.type = 'hidden'; input.name = 'excluded[]'; input.value = box.dataset.key;
                fields.appendChild(input);
            });
            var title = zone.parentElement.previousElementSibling;
            if (title) { title.style.opacity = (boxes.length && !anyChecked) ? '.3' : ''; }
        });
    }

    function bindFrame() {
        var doc = frame.contentDocument;
        if (!doc || !doc.querySelector('[data-zone]')) { return; }
        doc.addEventListener('change', function () { syncChecks(doc); });
        syncChecks(doc);
    }
    frame.addEventListener('load', bindFrame);
    if (frame.contentDocument && frame.contentDocument.readyState === 'complete') { bindFrame(); }

    // Confirmation quand le destinataire n'est pas l'admin connecté (liste de diffusion possible).
    form.addEventListener('submit', function (event) {
        if (select.value !== form.dataset.ownEmail
            && !confirm('Envoyer la newsletter à ' + select.value + ' ? Si c\'est une liste de diffusion, tous ses membres la recevront.')) {
            event.preventDefault();
        }
    });
})();
