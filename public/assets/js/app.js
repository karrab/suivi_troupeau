/* Suivi Troupeau — scripts (aucun script inline : compatible CSP stricte) */
(function () {
    'use strict';
    const appScript = document.getElementById('app-js');
    const searchUrl = appScript ? appScript.dataset.searchUrl : null;
    const testUrl = appScript ? appScript.dataset.testUrl : null;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ---- Confirmation de suppression (modal Bootstrap)
    let pendingForm = null;
    const modalEl = document.getElementById('confirmModal');
    const modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
    document.addEventListener('submit', function (ev) {
        const form = ev.target;
        if (!form.matches('form[data-confirm]') || form.dataset.confirmed === '1') return;
        ev.preventDefault();
        pendingForm = form;
        if (modal) {
            document.getElementById('confirmMessage').textContent = form.dataset.confirm;
            modal.show();
        } else if (window.confirm(form.dataset.confirm)) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
    document.getElementById('confirmOk')?.addEventListener('click', function () {
        if (pendingForm) {
            pendingForm.dataset.confirmed = '1';
            pendingForm.submit();
        }
    });

    // ---- Impression
    document.querySelectorAll('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));

    // ---- Soumission automatique des filtres
    document.querySelectorAll('[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form.submit()));

    // ---- Autocomplétion des n° de boucle
    const datalist = document.getElementById('animal-tags');
    let timer = null;
    document.querySelectorAll('input[data-animal-search]').forEach(function (input) {
        input.setAttribute('list', 'animal-tags');
        input.setAttribute('autocomplete', 'off');
        input.addEventListener('input', function () {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 1 || !searchUrl) return;
            timer = setTimeout(function () {
                const params = new URLSearchParams({ q: q });
                if (input.dataset.sex) params.set('sex', input.dataset.sex);
                if (input.dataset.all) params.set('all', '1');
                fetch(searchUrl + '&' + params.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : [])
                    .then(function (items) {
                        datalist.innerHTML = '';
                        items.forEach(function (it) {
                            const o = document.createElement('option');
                            o.value = it.tag;
                            o.label = it.label;
                            o.textContent = it.label;
                            datalist.appendChild(o);
                        });
                    }).catch(() => {});
            }, 200);
        });
    });

    // ---- Onglets : ouverture via ?tab=xxx
    const tab = new URLSearchParams(location.search).get('tab');
    if (tab && window.bootstrap) {
        const trigger = document.querySelector('[data-bs-target="#tab-' + tab + '"]');
        if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
    }

    // ---- Graphiques (Chart.js) : <canvas data-chart='{"type":..., "labels":[...], "datasets":[...]}'>
    if (window.Chart) {
        document.querySelectorAll('canvas[data-chart]').forEach(function (c) {
            try {
                const cfg = JSON.parse(c.dataset.chart);
                new Chart(c, {
                    type: cfg.type || 'line',
                    data: { labels: cfg.labels, datasets: cfg.datasets },
                    options: Object.assign({
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: (cfg.datasets || []).length > 1 } },
                        scales: cfg.type === 'doughnut' ? {} : { y: { beginAtZero: !!cfg.beginAtZero } }
                    }, cfg.options || {})
                });
            } catch (e) { console.error(e); }
        });
    }

    // ---- Formules : insertion de variable + test sur données réelles
    const formula = document.getElementById('formula');
    document.querySelectorAll('.var-badge').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!formula) return;
            const start = formula.selectionStart ?? formula.value.length;
            const end = formula.selectionEnd ?? formula.value.length;
            const txt = b.dataset.var;
            formula.value = formula.value.slice(0, start) + txt + formula.value.slice(end);
            formula.focus();
            formula.selectionStart = formula.selectionEnd = start + txt.length;
        });
    });
    const scopeSel = document.getElementById('scope');
    function toggleVars() {
        if (!scopeSel) return;
        document.querySelectorAll('[data-vars-scope]').forEach(el => el.classList.toggle('d-none', el.dataset.varsScope !== scopeSel.value));
    }
    scopeSel?.addEventListener('change', toggleVars);
    toggleVars();

    document.getElementById('btnTestFormula')?.addEventListener('click', function () {
        const form = this.closest('form');
        const out = document.getElementById('testResult');
        const fd = new FormData(form);
        fd.set('year', document.getElementById('testYear')?.value || '');
        out.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Calcul...</div>';
        fetch(testUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function (res) {
                if (!res.ok) {
                    out.innerHTML = '<div class="alert alert-danger py-2 mb-0"></div>';
                    out.firstChild.textContent = res.error;
                    return;
                }
                const colors = { bon: 'success', moyen: 'warning', faible: 'danger', na: 'secondary' };
                const table = document.createElement('table');
                table.className = 'table table-sm mb-0';
                table.innerHTML = '<thead><tr><th>Groupe / animal (' + (res.year || 'vie entière') + ')</th><th>Valeur</th><th>Interprétation</th></tr></thead><tbody></tbody>';
                res.results.forEach(function (r) {
                    const tr = document.createElement('tr');
                    const td1 = document.createElement('td'); td1.textContent = r.label;
                    const td2 = document.createElement('td'); td2.textContent = r.value;
                    const td3 = document.createElement('td');
                    const badge = document.createElement('span');
                    badge.className = 'badge text-bg-' + (colors[r.level] || 'secondary') + ' me-1';
                    badge.textContent = r.level;
                    td3.appendChild(badge);
                    td3.appendChild(document.createTextNode(r.text));
                    tr.append(td1, td2, td3);
                    table.tBodies[0].appendChild(tr);
                });
                if (!res.results.length) {
                    table.tBodies[0].innerHTML = '<tr><td colspan="3" class="text-muted">Aucune donnée pour cette période.</td></tr>';
                }
                out.innerHTML = '';
                out.appendChild(table);
            })
            .catch(() => { out.innerHTML = '<div class="alert alert-danger py-2 mb-0">Erreur de communication.</div>'; });
    });

    // ---- Animal : champs de sortie visibles seulement si statut ≠ actif
    const status = document.getElementById('status');
    function toggleExit() {
        document.querySelectorAll('.exit-fields').forEach(el => el.classList.toggle('d-none', status.value === 'actif'));
    }
    if (status) { status.addEventListener('change', toggleExit); toggleExit(); }

    // ---- Animal : achat => prix d'achat
    const origin = document.getElementById('origin');
    function toggleOrigin() {
        document.querySelectorAll('.purchase-fields').forEach(el => el.classList.toggle('d-none', origin.value !== 'achat'));
    }
    if (origin) { origin.addEventListener('change', toggleOrigin); toggleOrigin(); }

    // ---- Calcul d'indices : cible troupeau / animal
    const target = document.getElementById('target');
    function toggleTarget() {
        document.querySelectorAll('[data-target-scope]').forEach(function (el) {
            const hidden = el.dataset.targetScope !== target.value;
            el.classList.toggle('d-none', hidden);
            el.querySelectorAll('input, select').forEach(i => { i.disabled = hidden; });
        });
    }
    if (target) { target.addEventListener('change', toggleTarget); toggleTarget(); }

    // ---- Rôles : cocher toute une ligne / colonne
    document.querySelectorAll('[data-check-row]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            document.querySelectorAll('input[data-module="' + cb.dataset.checkRow + '"]').forEach(x => { x.checked = cb.checked; });
        });
    });
})();
