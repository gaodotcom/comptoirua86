(function () {
    'use strict';

    window.setView = function (view) {
        var list = document.getElementById('membersList');
        var btnCards = document.getElementById('viewCards');
        var btnList = document.getElementById('viewList');
        if (view === 'list' && list.classList.contains('trombi-cards')) {
            list.classList.remove('trombi-cards', 'row', 'g-3');
            list.classList.add('trombi-list', 'list-group', 'list-group-flush', 'trombi-list-top');
            btnCards.classList.remove('btn-primary', 'active');
            btnCards.classList.add('btn-outline-primary');
            btnCards.disabled = false;
            btnList.classList.remove('btn-outline-primary');
            btnList.classList.add('btn-primary', 'active');
            btnList.disabled = true;
            localStorage.setItem('trombiView', 'list');
        } else if (view === 'cards' && list.classList.contains('trombi-list')) {
            list.classList.remove('trombi-list', 'list-group', 'list-group-flush', 'trombi-list-top');
            list.classList.add('trombi-cards', 'row', 'g-3');
            btnList.classList.remove('btn-primary', 'active');
            btnList.classList.add('btn-outline-primary');
            btnList.disabled = false;
            btnCards.classList.remove('btn-outline-primary');
            btnCards.classList.add('btn-primary', 'active');
            btnCards.disabled = true;
            localStorage.setItem('trombiView', 'cards');
        } else {
            return;
        }
        document.querySelectorAll('.trombi-item').forEach(function (item) {
            item.classList.toggle('col-6');
            item.classList.toggle('col-lg-3');
        });
    };

    window.toggleRenewalFilter = function () {
        var btn = document.getElementById('renewalFilter');
        var icon = document.getElementById('renewalFilterIcon');
        var items = document.querySelectorAll('.trombi-item');
        var active = btn.classList.toggle('active-filter');
        if (active) {
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-primary', 'active');
            icon.classList.remove('bi-hourglass-split');
            icon.classList.add('bi-check2-circle');
        } else {
            btn.classList.remove('btn-primary', 'active');
            btn.classList.add('btn-danger');
            icon.classList.remove('bi-check2-circle');
            icon.classList.add('bi-hourglass-split');
        }
        items.forEach(function (item) {
            if (active) {
                item.style.display = item.dataset.renewal === '1' ? '' : 'none';
            } else {
                item.style.display = '';
            }
        });
        if (active) { btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (localStorage.getItem('trombiView') === 'list') { setView('list'); }
    });
})();
