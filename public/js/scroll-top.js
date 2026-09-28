(function () {
    'use strict';

    window.addEventListener('scroll', function () {
        var btn = document.getElementById('scrollTop');
        if (btn) { btn.style.display = window.scrollY > 300 ? 'flex' : 'none'; }
    });

    // En mode standalone (app lancée depuis l'écran d'accueil), le système
    // restaure souvent la page suspendue telle quelle (scroll compris) au lieu
    // de la recharger. On détecte cette restauration via pageshow/persisted et
    // on repart en haut de page.
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (isStandalone) {
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) { window.scrollTo(0, 0); }
        });
    }
})();
