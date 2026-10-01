(function () {
    'use strict';

    var ultraCheckbox = document.getElementById('course_ultra');
    var otherCourses = document.getElementById('otherCourses');
    if (!ultraCheckbox || !otherCourses) {
        // Choix de course verrouillé (coéquipier de duo désigné par quelqu'un d'autre) :
        // ces éléments ne sont pas rendus, rien à faire.
        return;
    }
    var ultraOptions = document.getElementById('ultraOptions');
    var otherInputs = otherCourses.querySelectorAll('input');
    var skyraceCheckbox = document.getElementById('course_skyrace');
    var sundayNoneRadio = document.getElementById('course_sunday_none');
    var saltaRadio = document.getElementById('course_salta');
    var sundayLunchCheckbox = document.getElementById('sunday_lunch');
    var duoRadio = document.getElementById('team_mode_duo');
    var soloRadio = document.getElementById('team_mode_solo');
    var duoPartnerField = document.getElementById('duoPartnerField');

    function isOtherCourseSelected() {
        return skyraceCheckbox.checked || !sundayNoneRadio.checked;
    }

    function updateUltraState() {
        var isUltra = ultraCheckbox.checked;
        ultraOptions.style.display = isUltra ? '' : 'none';
        otherInputs.forEach(function (input) { input.disabled = isUltra; });
        updateSundayLunchState();
    }

    function updateOtherCoursesState() {
        ultraCheckbox.disabled = isOtherCourseSelected();
    }

    // Repas du dimanche midi : réservé aux coureurs du Salta Bartas, ou de la
    // Skyrace s'ils ne font pas de course le dimanche (cf. validation serveur
    // dans validate_weekend_2027_payload()).
    function updateSundayLunchState() {
        var eligible = !ultraCheckbox.checked
            && (saltaRadio.checked || (skyraceCheckbox.checked && sundayNoneRadio.checked));
        sundayLunchCheckbox.disabled = !eligible;
        if (!eligible) {
            sundayLunchCheckbox.checked = false;
        }
    }

    function updateDuoState() {
        duoPartnerField.style.display = duoRadio.checked ? '' : 'none';
    }

    ultraCheckbox.addEventListener('change', updateUltraState);
    duoRadio.addEventListener('change', updateDuoState);
    soloRadio.addEventListener('change', updateDuoState);
    otherInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            updateOtherCoursesState();
            updateSundayLunchState();
        });
    });

    updateUltraState();
    updateOtherCoursesState();
    updateSundayLunchState();
    updateDuoState();
})();
