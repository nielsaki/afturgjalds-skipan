(function () {
    'use strict';

    var form = document.getElementById('afs-form');
    if (!form) { return; }

    var list     = document.getElementById('afs-lines-list');
    var addBtn   = document.getElementById('afs-add-line');
    var template = document.getElementById('afs-line-template');
    var totalEl  = document.getElementById('afs-total');

    var data     = (typeof window.AFS_DATA === 'object' && window.AFS_DATA) ? window.AFS_DATA : {};
    var rate     = typeof data.ratePerKm !== 'undefined' ? parseFloat(data.ratePerKm) : 1.60;
    var tunnels  = data.tunnels && typeof data.tunnels === 'object' ? data.tunnels : {};

    function nextIndex() {
        var max = -1;
        list.querySelectorAll('.afs-line').forEach(function (el) {
            var n = parseInt(el.getAttribute('data-index'), 10);
            if (!isNaN(n) && n > max) { max = n; }
        });
        return max + 1;
    }

    function renumberVisible() {
        var lines = list.querySelectorAll('.afs-line');
        lines.forEach(function (el, i) {
            var numEl = el.querySelector('.afs-line__num');
            if (numEl) { numEl.textContent = (i + 1); }
        });
    }

    function toggleTypeSections(line) {
        var sel = line.querySelector('.afs-line__type');
        if (!sel) { return; }
        var active = sel.value;
        line.querySelectorAll('.afs-line__type-section').forEach(function (sec) {
            sec.style.display = sec.getAttribute('data-type') === active ? '' : 'none';
        });
    }

    function parseNum(v) {
        if (v == null) { return 0; }
        var s = String(v).trim().replace(',', '.');
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    function lineAmount(line) {
        var sel = line.querySelector('.afs-line__type');
        var type = sel ? sel.value : 'driving';

        if (type === 'driving') {
            var claim = line.querySelector('[data-afs-km-claim]');
            var kmInput = line.querySelector('[data-afs-driving-km]');
            var km = parseNum(kmInput ? kmInput.value : '0');
            var total = (claim && claim.checked && km > 0) ? km * rate : 0;

            line.querySelectorAll('input[name*="[tunnels]"]').forEach(function (inp) {
                var name = inp.getAttribute('name') || '';
                var m = name.match(/\[tunnels\]\[(.+?)\]/);
                if (!m) { return; }
                var key = m[1];
                var count = parseInt(inp.value || '0', 10);
                if (!isNaN(count) && count > 0 && tunnels[key]) {
                    total += tunnels[key] * count;
                }
            });

            return total;
        }

        var amtInput = line.querySelector('[data-afs-amount]');
        var amt = parseNum(amtInput ? amtInput.value : '0');
        return amt > 0 ? amt : 0;
    }

    function updateTotal() {
        if (!totalEl) { return; }
        var total = 0;
        list.querySelectorAll('.afs-line').forEach(function (line) {
            total += lineAmount(line);
        });
        totalEl.value = total > 0 ? (total.toFixed(2).replace('.', ',') + ' kr') : '';
    }

    function addLine() {
        if (!template) { return; }
        var idx  = nextIndex();
        var html = template.innerHTML.replace(/__INDEX__/g, String(idx));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var node = wrap.firstElementChild;
        if (!node) { return; }
        node.setAttribute('data-index', String(idx));
        list.appendChild(node);
        toggleTypeSections(node);
        syncKmClaim(node);
        renumberVisible();
        updateTotal();
    }

    function removeLine(el) {
        if (list.querySelectorAll('.afs-line').length <= 1) {
            return;
        }
        el.parentNode.removeChild(el);
        renumberVisible();
        updateTotal();
    }

    function syncKmClaim(line) {
        var cb = line.querySelector('[data-afs-km-claim]');
        if (!cb) { return; }
        var km    = line.querySelector('[data-afs-driving-km]');
        var wrap  = line.querySelector('.afs-km-input');
        var req   = line.querySelector('.afs-km-req');
        if (km) {
            if (cb.checked) { km.setAttribute('required', ''); }
            else            { km.removeAttribute('required'); }
        }
        if (wrap) { wrap.classList.toggle('afs-km-input--off', !cb.checked); }
        if (req)  { req.toggleAttribute('hidden', !cb.checked); }
    }

    list.addEventListener('change', function (e) {
        if (e.target && e.target.classList) {
            if (e.target.classList.contains('afs-line__type')) {
                var line = e.target.closest('.afs-line');
                if (line) { toggleTypeSections(line); }
            }
            if (e.target.matches && e.target.matches('[data-afs-km-claim]')) {
                var line2 = e.target.closest('.afs-line');
                if (line2) { syncKmClaim(line2); }
            }
        }
        updateTotal();
    });
    list.addEventListener('input', updateTotal);
    list.addEventListener('click', function (e) {
        var t = e.target;
        if (t && t.classList && t.classList.contains('afs-remove-line')) {
            var line = t.closest('.afs-line');
            if (line) { removeLine(line); }
        }
    });
    if (addBtn) { addBtn.addEventListener('click', addLine); }

    list.querySelectorAll('.afs-line').forEach(function (line) {
        toggleTypeSections(line);
        syncKmClaim(line);
    });
    updateTotal();
})();
