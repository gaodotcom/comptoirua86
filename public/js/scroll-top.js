(function () {
    'use strict';

    window.addEventListener('scroll', function () {
        var btn = document.getElementById('scrollTop');
        if (btn) { btn.style.display = window.scrollY > 300 ? 'flex' : 'none'; }
    });
})();
