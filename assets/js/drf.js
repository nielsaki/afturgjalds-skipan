(function () {
    'use strict';

    var form = document.getElementById('afs-form');
    if (!form) { return; }

    var list     = document.getElementById('afs-lines-list');
    if (!list) { return; }
    var addBtn   = document.getElementById('afs-add-line');
    var template = document.getElementById('afs-line-template');
    var totalEl  = document.getElementById('afs-total');

    var data     = (typeof window.AFS_DATA === 'object' && window.AFS_DATA) ? window.AFS_DATA : {};
    var rate     = typeof data.ratePerKm !== 'undefined' ? parseFloat(data.ratePerKm) : 1.60;
    var tunnels  = data.tunnels && typeof data.tunnels === 'object' ? data.tunnels : {};
    var phDesc   = (data.placeholders && data.placeholders.description) ? data.placeholders.description : {};
    var phNoteDr = (data.placeholders && data.placeholders.noteDriving) ? data.placeholders.noteDriving : '';

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
            var match = sec.getAttribute('data-type') === active;
            sec.style.display = match ? '' : 'none';
            // Disable fields in inactive sections so hidden required fields don't block
            // submit and their values aren't sent for the wrong type.
            sec.querySelectorAll('input, select, textarea').forEach(function (el) {
                el.disabled = !match;
            });
        });
        // When the driving section becomes active again, re-apply the
        // km-claim readonly/required logic (since we just re-enabled all
        // of its fields in bulk above).
        if (active === 'driving') {
            syncKmClaim(line);
        }
        syncLinePlaceholders(line);
    }

    function syncLinePlaceholders(line) {
        var sel = line.querySelector('.afs-line__type');
        var type = sel ? sel.value : '';
        var desc = line.querySelector('.afs-line__desc');
        var note = line.querySelector('.afs-line__note');
        if (desc) {
            desc.placeholder = (type && phDesc[type]) ? phDesc[type] : '';
        }
        if (note) {
            note.placeholder = (type === 'driving' && phNoteDr) ? phNoteDr : '';
        }
    }

    function parseNum(v) {
        if (v == null) { return 0; }
        var s = String(v).trim().replace(',', '.');
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    function lineAmount(line) {
        var sel = line.querySelector('.afs-line__type');
        var type = sel ? sel.value : '';
        if (!type) { return 0; }

        var section = line.querySelector('.afs-line__type-section[data-type="' + type + '"]');
        if (!section) { return 0; }

        if (type === 'driving') {
            var claim = section.querySelector('[data-afs-km-claim]');
            var kmInput = section.querySelector('[data-afs-driving-km]');
            var km = parseNum(kmInput ? kmInput.value : '0');
            var total = (claim && claim.checked && km > 0) ? km * rate : 0;

            section.querySelectorAll('input[name*="[tunnels]"]').forEach(function (inp) {
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

        var amtInput = section.querySelector('[data-afs-amount]');
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
        var km   = line.querySelector('[data-afs-driving-km]');
        var wrap = line.querySelector('.afs-km-input');
        var req  = line.querySelector('.afs-km-req');
        var on   = !!cb.checked;
        // Show the km block only when the claim checkbox is ticked. Use a
        // class + hidden attr so aggressive theme CSS cannot leave the row
        // invisible after unchecking [hidden] alone.
        if (wrap) {
            if (on) {
                wrap.classList.remove('afs-km-input--collapsed');
                wrap.removeAttribute('hidden');
                wrap.style.removeProperty('display');
            } else {
                wrap.classList.add('afs-km-input--collapsed');
                wrap.setAttribute('hidden', '');
            }
        }
        if (req)  { req.toggleAttribute('hidden', !on); }
        if (km) {
            if (on) { km.setAttribute('required', ''); }
            else    { km.removeAttribute('required'); }
        }
    }

    function isKmClaimCheckbox(el) {
        if (!el || !el.getAttribute) { return false; }
        if (el.getAttribute('data-afs-km-claim') !== null) { return true; }
        var n = el.name;
        return typeof n === 'string' && n.indexOf('[km_claim]') !== -1;
    }

    list.addEventListener('change', function (e) {
        var t = e.target;
        if (t && t.classList && t.classList.contains('afs-line__type')) {
            var line = t.closest('.afs-line');
            if (line) { toggleTypeSections(line); }
        }
        if (isKmClaimCheckbox(t)) {
            var line2 = t.closest('.afs-line');
            if (line2) { syncKmClaim(line2); }
        }
        updateTotal();
    });
    list.addEventListener('input', function (e) {
        var t = e.target;
        if (isKmClaimCheckbox(t)) {
            var line2 = t.closest('.afs-line');
            if (line2) { syncKmClaim(line2); }
        }
        updateTotal();
    });
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
