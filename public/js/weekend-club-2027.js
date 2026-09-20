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
    }

    function updateOtherCoursesState() {
        ultraCheckbox.disabled = isOtherCourseSelected();
    }

    function updateDuoState() {
        duoPartnerField.style.display = duoRadio.checked ? '' : 'none';
    }

    ultraCheckbox.addEventListener('change', updateUltraState);
    duoRadio.addEventListener('change', updateDuoState);
    soloRadio.addEventListener('change', updateDuoState);
    otherInputs.forEach(function (input) { input.addEventListener('change', updateOtherCoursesState); });

    updateUltraState();
    updateOtherCoursesState();
    updateDuoState();
})();
