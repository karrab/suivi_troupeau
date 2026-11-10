/* Suivi Troupeau — scripts (aucun script inline : compatible avec une CSP stricte) */
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ---- Confirmation avant suppression (fenêtre modale Bootstrap)
    let formEnAttente = null;
    const modalEl = document.getElementById('fenetreConfirmation');
    const modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
    document.addEventListener('submit', function (ev) {
        const f = ev.target;
        if (!f.matches('form[data-confirmer]') || f.dataset.confirme === '1') return;
        ev.preventDefault();
        formEnAttente = f;
        if (modal) {
            document.getElementById('messageConfirmation').textContent = f.dataset.confirmer;
            modal.show();
        } else if (window.confirm(f.dataset.confirmer)) {
            f.dataset.confirme = '1';
            f.submit();
        }
    });
    document.getElementById('boutonConfirmer')?.addEventListener('click', function () {
        if (formEnAttente) { formEnAttente.dataset.confirme = '1'; formEnAttente.submit(); }
    });

    // ---- Impression
    document.querySelectorAll('[data-imprimer]').forEach(b => b.addEventListener('click', () => window.print()));

    // ---- Envoi automatique des listes déroulantes de filtres
    document.querySelectorAll('[data-auto]').forEach(s => s.addEventListener('change', () => s.form.submit()));

    // ---- Autocomplétion des numéros de boucle
    const liste = document.getElementById('liste-boucles');
    let minuterie = null;
    document.querySelectorAll('input[data-recherche-animal]').forEach(function (champ) {
        champ.setAttribute('list', 'liste-boucles');
        champ.setAttribute('autocomplete', 'off');
        champ.addEventListener('input', function () {
            clearTimeout(minuterie);
            const q = champ.value.trim();
            if (!q || !liste) return;
            minuterie = setTimeout(function () {
                const p = new URLSearchParams({ q: q });
                if (champ.dataset.sexe) p.set('sexe', champ.dataset.sexe);
                if (champ.dataset.tous) p.set('tous', '1');
                fetch('animaux_recherche.php?' + p.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : [])
                    .then(function (items) {
                        liste.innerHTML = '';
                        items.forEach(function (it) {
                            const o = document.createElement('option');
                            o.value = it.boucle;
                            o.label = it.libelle;
                            o.textContent = it.libelle;
                            liste.appendChild(o);
                        });
                    }).catch(() => {});
            }, 200);
        });
    });

    // ---- Onglets : ouverture directe avec ?onglet=xxx
    const onglet = new URLSearchParams(location.search).get('onglet');
    if (onglet && window.bootstrap) {
        const b = document.querySelector('[data-bs-target="#onglet-' + onglet + '"]');
        if (b) bootstrap.Tab.getOrCreateInstance(b).show();
    }

    // ---- Graphiques Chart.js : <canvas data-graphique='{"type":..,"labels":[..],"datasets":[..]}'>
    if (window.Chart) {
        document.querySelectorAll('canvas[data-graphique]').forEach(function (c) {
            try {
                const g = JSON.parse(c.dataset.graphique);
                new Chart(c, {
                    type: g.type || 'line',
                    data: { labels: g.labels, datasets: g.datasets },
                    options: Object.assign({
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: (g.datasets || []).length > 1 } },
                        scales: g.type === 'doughnut' ? {} : { y: { beginAtZero: !!g.zero } }
                    }, g.options || {})
                });
            } catch (e) { console.error(e); }
        });
    }

    // ---- Formules : insertion d'une variable au curseur
    const formule = document.getElementById('formule');
    document.querySelectorAll('.badge-variable').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!formule) return;
            const d = formule.selectionStart ?? formule.value.length;
            const f = formule.selectionEnd ?? formule.value.length;
            formule.value = formule.value.slice(0, d) + b.dataset.var + formule.value.slice(f);
            formule.focus();
            formule.selectionStart = formule.selectionEnd = d + b.dataset.var.length;
        });
    });
    const portee = document.getElementById('portee');
    function basculerVariables() {
        document.querySelectorAll('[data-portee-vars]').forEach(el => el.classList.toggle('d-none', el.dataset.porteeVars !== portee.value));
    }
    if (portee) { portee.addEventListener('change', basculerVariables); basculerVariables(); }

    // ---- Formules : test sur les données réelles (AJAX)
    document.getElementById('btnTester')?.addEventListener('click', function () {
        const form = this.closest('form');
        const sortie = document.getElementById('resultatTest');
        const fd = new FormData(form);
        fd.set('annee', document.getElementById('anneeTest')?.value || '');
        sortie.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Calcul...</div>';
        fetch('formule_tester.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function (res) {
                if (!res.ok) {
                    sortie.innerHTML = '<div class="alert alert-danger py-2 mb-0"></div>';
                    sortie.firstChild.textContent = res.erreur;
                    return;
                }
                const couleurs = { bon: 'success', moyen: 'warning', faible: 'danger', nd: 'secondary' };
                const t = document.createElement('table');
                t.className = 'table table-sm mb-0';
                t.innerHTML = '<thead><tr><th>Groupe / animal (' + (res.annee || 'vie entière') + ')</th><th>Valeur</th><th>Interprétation</th></tr></thead><tbody></tbody>';
                res.resultats.forEach(function (r) {
                    const tr = document.createElement('tr');
                    const a = document.createElement('td'); a.textContent = r.libelle;
                    const b = document.createElement('td'); b.textContent = r.valeur;
                    const c = document.createElement('td');
                    const badge = document.createElement('span');
                    badge.className = 'badge text-bg-' + (couleurs[r.niveau] || 'secondary') + ' me-1';
                    badge.textContent = r.niveau;
                    c.append(badge, document.createTextNode(r.texte));
                    tr.append(a, b, c);
                    t.tBodies[0].appendChild(tr);
                });
                if (!res.resultats.length) t.tBodies[0].innerHTML = '<tr><td colspan="3" class="text-muted">Aucune donnée pour cette période.</td></tr>';
                sortie.innerHTML = '';
                sortie.appendChild(t);
            })
            .catch(() => { sortie.innerHTML = '<div class="alert alert-danger py-2 mb-0">Erreur de communication.</div>'; });
    });

    // ---- Fiche animal : champs de sortie / d'achat selon le statut et l'origine
    function basculer(selectId, classe, condition) {
        const s = document.getElementById(selectId);
        if (!s) return;
        const maj = () => document.querySelectorAll(classe).forEach(el => el.classList.toggle('d-none', !condition(s.value)));
        s.addEventListener('change', maj);
        maj();
    }
    basculer('statut', '.champs-sortie', v => v !== 'actif');
    basculer('origine', '.champs-achat', v => v === 'achat');

    // ---- Calcul d'indices : cible troupeau / animal (désactive les champs masqués)
    const cible = document.getElementById('cible');
    function basculerCible() {
        document.querySelectorAll('[data-cible]').forEach(function (el) {
            const cache = el.dataset.cible !== cible.value;
            el.classList.toggle('d-none', cache);
            el.querySelectorAll('input, select').forEach(i => { i.disabled = cache; });
        });
    }
    if (cible) { cible.addEventListener('change', basculerCible); basculerCible(); }

    // ---- Rôles : cocher toute une ligne de permissions
    document.querySelectorAll('[data-cocher-ligne]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            document.querySelectorAll('input[data-module="' + cb.dataset.cocherLigne + '"]').forEach(x => { x.checked = cb.checked; });
        });
    });
})();
