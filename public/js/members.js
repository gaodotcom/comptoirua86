(function () {
    'use strict';

    function removeAccents(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    var sortDir = {};

    window.sortMembers = function (key, options) {
        var list = document.getElementById('membersList');
        if (!list) { return; }
        var items = Array.from(list.children);
        var dir = sortDir[key] === 'asc' ? 'desc' : 'asc';
        sortDir = {};
        sortDir[key] = dir;
        var sortGeneric = options && options.sortGeneric;

        items.sort(function (a, b) {
            if (sortGeneric) {
                var ga = a.dataset.generic || '0';
                var gb = b.dataset.generic || '0';
                if (ga !== gb) { return ga === '1' ? 1 : -1; }
            }
            var va = a.dataset[key] || '';
            var vb = b.dataset[key] || '';
            return dir === 'asc' ? va.localeCompare(vb, 'fr') : vb.localeCompare(va, 'fr');
        });

        items.forEach(function (item) { list.appendChild(item); });

        document.querySelectorAll('[data-sort]').forEach(function (btn) {
            btn.classList.remove('active', 'btn-primary');
            btn.classList.add('btn-outline-primary');
            var arrow = btn.querySelector('[data-arrow]');
            if (arrow) { arrow.className = 'bi bi-arrow-down-up ms-1'; }
        });
        var activeBtn = document.querySelector('[data-sort="' + key + '"]');
        if (activeBtn) {
            activeBtn.classList.remove('btn-outline-primary');
            activeBtn.classList.add('btn-primary', 'active');
            var activeArrow = activeBtn.querySelector('[data-arrow]');
            if (activeArrow) {
                activeArrow.className = dir === 'asc' ? 'bi bi-sort-alpha-down ms-1' : 'bi bi-sort-alpha-up ms-1';
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var search = document.getElementById('memberSearch');
        if (!search) { return; }

        var itemSelector = (document.getElementById('membersList') || {}).dataset.itemSelector || '#membersList > div';
        if (itemSelector === '__auto__') {
            var list = document.getElementById('membersList');
            itemSelector = list && list.classList.contains('list-group') ? '#membersList > .list-group-item' : '#membersList > div';
        }

        search.addEventListener('input', function () {
            var query = removeAccents(this.value.trim().toLowerCase());
            var items = document.querySelectorAll(itemSelector);
            items.forEach(function (item) {
                var fn = removeAccents(item.dataset.first_name || '');
                var ln = removeAccents(item.dataset.last_name || '');
                var match = query === '' || fn.includes(query) || ln.includes(query) || (fn + ' ' + ln).includes(query);
                item.style.display = match ? '' : 'none';
            });
        });
    });

    document.querySelectorAll('.reactivate-toggle').forEach(function (toggle) {
        var target = document.querySelector(toggle.dataset.bsTarget);
        var badge = toggle.closest('.d-flex').querySelector('.inactive-badge');
        if (!target) { return; }
        target.addEventListener('show.bs.collapse', function () {
            if (badge) {
                badge.classList.remove('text-bg-danger');
                badge.classList.add('text-bg-primary');
            }
        });
        target.addEventListener('hide.bs.collapse', function () {
            if (badge) {
                badge.classList.remove('text-bg-primary');
                badge.classList.add('text-bg-danger');
            }
        });
    });
})();
