(function () {
    'use strict';

    var STORAGE_KEY = 'calendarHiddenTypes';

    function getHiddenTypes() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveHiddenTypes(types) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(types));
        } catch (e) {
            // Stockage indisponible (navigation privée...) : le filtre reste actif
            // pour la session en cours mais ne sera pas mémorisé, tant pis.
        }
    }

    function applyFilter(type, hidden) {
        document.querySelectorAll('[data-cal-type="' + type + '"]').forEach(function (el) {
            el.style.display = hidden ? 'none' : '';
        });
    }

    window.toggleCalendarFilter = function (type) {
        var badge = document.querySelector('.calendar-legend [data-cal-filter="' + type + '"]');
        var hiddenTypes = getHiddenTypes();
        var idx = hiddenTypes.indexOf(type);
        var nowHidden = idx === -1;

        if (nowHidden) {
            hiddenTypes.push(type);
        } else {
            hiddenTypes.splice(idx, 1);
        }

        saveHiddenTypes(hiddenTypes);
        if (badge) { badge.classList.toggle('filter-off', nowHidden); }
        applyFilter(type, nowHidden);
    };

    document.addEventListener('DOMContentLoaded', function () {
        getHiddenTypes().forEach(function (type) {
            var badge = document.querySelector('.calendar-legend [data-cal-filter="' + type + '"]');
            if (badge) { badge.classList.add('filter-off'); }
            applyFilter(type, true);
        });

        // Les badges sont des <span role="button">, pas de vrais <button> :
        // pas d'activation clavier native, on l'ajoute pour Entrée/Espace.
        document.querySelectorAll('.calendar-legend [data-cal-filter]').forEach(function (badge) {
            badge.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    badge.click();
                }
            });
        });
    });
})();
