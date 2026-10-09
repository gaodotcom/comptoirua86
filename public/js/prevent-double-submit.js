// Évite le double envoi (double tap, page lente) : après le premier submit,
// le bouton est désactivé et affiche « En cours… » (ou data-submitting-text).
// data-submitting-hint ajoute une petite phrase sous le bouton.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.submitted) {
                e.preventDefault();
                return;
            }
            form.dataset.submitted = '1';
            var btn = form.querySelector('button[type="submit"]');
            if (!btn) {
                return;
            }
            // Fige la largeur pour que le bouton ne bouge pas en changeant de texte.
            btn.style.minWidth = btn.offsetWidth + 'px';
            btn.disabled = true;
            btn.textContent = btn.dataset.submittingText || 'En cours…';
            if (btn.dataset.submittingHint) {
                var hint = document.createElement('div');
                hint.className = 'text-muted small mt-2';
                hint.textContent = btn.dataset.submittingHint;
                btn.parentNode.appendChild(hint);
            }
        });
    });
});
