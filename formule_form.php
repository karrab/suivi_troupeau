<?php
/**
 * Création / modification d'un indice et de sa formule, avec test en direct.
 * POST action=supprimer : suppression de l'indice (et de ses résultats).
 * Chaque changement de formule est enregistré dans historique_formules.
 */
require __DIR__ . '/inc/init.php';
require INC . '/indices.php';
exiger_permission('indices.formules');

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$ancien = $id ? (bd_trouver('indices', $id) ?? abandonner(404, 'Indice introuvable.')) : null;

if (est_post() && ($_POST['action'] ?? '') === 'supprimer') {
    bd_supprimer('indices', $id);
    tracer('suppression', 'indice', $id, 'Suppression de l\'indice ' . $ancien['code'], $ancien, null);
    cache_invalider('indices');
    flash('success', 'Indice supprimé (ainsi que ses résultats).');
    rediriger('formules.php');
}

if (est_post()) {
    [$d, $err] = valider($_POST, [
        'code' => 'obligatoire|maxcar:40|motif:/^[a-z0-9_]+$/', 'nom' => 'obligatoire|maxcar:120', 'description' => 'maxcar:500',
        'portee' => 'obligatoire|dans:animal,troupeau', 'formule' => 'obligatoire|maxcar:1000', 'unite' => 'maxcar:20',
        'decimales' => 'obligatoire|entier|min:0|max:6', 'sens' => 'obligatoire|dans:haut,bas', 'seuil_bas' => 'nombre', 'seuil_haut' => 'nombre',
        'texte_bon' => 'maxcar:255', 'texte_moyen' => 'maxcar:255', 'texte_faible' => 'maxcar:255', 'actif' => 'case', 'ordre' => 'entier',
    ], ['code' => 'Code', 'nom' => 'Nom', 'formule' => 'Formule', 'portee' => 'Portée', 'decimales' => 'Décimales', 'seuil_bas' => 'Seuil bas', 'seuil_haut' => 'Seuil haut']);
    if (!$err) {
        $e = (new MoteurFormule())->valider((string) $d['formule'], array_keys(variables_portee($d['portee'])));
        if ($e) {
            $err['formule'] = 'Formule invalide : ' . $e;
        }
        if ($d['seuil_bas'] !== null && $d['seuil_haut'] !== null && (float) $d['seuil_bas'] > (float) $d['seuil_haut']) {
            $err['seuil_haut'] = 'Le seuil haut doit être supérieur ou égal au seuil bas.';
        }
        $doublon = bd_valeur('SELECT id FROM indices WHERE code = :c', ['c' => $d['code']]);
        if ($doublon && (int) $doublon !== $id) {
            $err['code'] = 'Ce code existe déjà.';
        }
    }
    if ($err) {
        garder_saisie($_POST, $err);
        rediriger(url('formule_form.php', $id ? ['id' => $id] : ['portee' => $_POST['portee'] ?? 'troupeau']));
    }
    foreach (['description', 'unite', 'texte_bon', 'texte_moyen', 'texte_faible'] as $k) {
        $d[$k] = ($d[$k] ?? '') === '' ? null : $d[$k];
    }
    $d['ordre'] = (int) ($d['ordre'] ?? 0);
    $d['modifie_par'] = utilisateur_id();
    bd_transaction(static function () use (&$id, $d, $ancien) {
        if ($ancien) {
            bd_modifier('indices', $d, $id);
            if ($ancien['formule'] !== $d['formule']) {
                bd_inserer('historique_formules', ['indice_id' => $id, 'ancienne_formule' => $ancien['formule'], 'nouvelle_formule' => $d['formule'], 'utilisateur_id' => utilisateur_id()]);
            }
        } else {
            $id = bd_inserer('indices', $d);
            bd_inserer('historique_formules', ['indice_id' => $id, 'ancienne_formule' => null, 'nouvelle_formule' => $d['formule'], 'utilisateur_id' => utilisateur_id()]);
        }
    });
    tracer($ancien ? 'modification' : 'ajout', 'indice', $id, 'Indice ' . $d['code'] . ($ancien && $ancien['formule'] !== $d['formule'] ? ' (formule modifiée)' : ''), $ancien, $d);
    cache_invalider('indices');
    flash('success', 'Indice « ' . $d['nom'] . ' » enregistré. Relancez le calcul pour mettre les résultats à jour.');
    rediriger('formules.php');
}

$def = $ancien ?? ['portee' => ($_GET['portee'] ?? '') === 'animal' ? 'animal' : 'troupeau', 'sens' => 'haut', 'decimales' => 2, 'actif' => 1, 'ordre' => 100];
$historique = $id ? bd_tous('SELECT h.*, u.nom_complet FROM historique_formules h LEFT JOIN utilisateurs u ON u.id = h.utilisateur_id
                            WHERE h.indice_id = :id ORDER BY h.modifie_le DESC, h.id DESC LIMIT 50', ['id' => $id]) : [];
$err = erreurs_form();
$titre = $ancien ? 'Modifier l\'indice ' . $ancien['nom'] : 'Nouvel indice';
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-calculator me-2"></i><?= e($titre) ?></h1>
    <a href="formules.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<?php if ($err): ?><div class="alert alert-danger">Veuillez corriger les erreurs signalées.</div><?php endif; ?>
<form method="post" action="formule_form.php" novalidate>
    <?= champ_csrf() ?>
    <?php if ($ancien): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3"><div class="card-header">Définition</div><div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label">Code *</label><input name="code" class="form-control<?= invalide($err, 'code') ?>" value="<?= e(fv($def, 'code')) ?>"><?= erreur_champ($err, 'code') ?><div class="form-text">minuscules, chiffres, _</div></div>
                <div class="col-md-8"><label class="form-label">Nom *</label><input name="nom" class="form-control<?= invalide($err, 'nom') ?>" value="<?= e(fv($def, 'nom')) ?>"><?= erreur_champ($err, 'nom') ?></div>
                <div class="col-12"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= e(fv($def, 'description')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Portée *</label><select name="portee" id="portee" class="form-select"><?= options(libelles('portee'), fv($def, 'portee')) ?></select></div>
                <div class="col-md-3"><label class="form-label">Unité</label><input name="unite" class="form-control" value="<?= e(fv($def, 'unite')) ?>"></div>
                <div class="col-md-2"><label class="form-label">Décimales</label><input type="number" min="0" max="6" name="decimales" class="form-control<?= invalide($err, 'decimales') ?>" value="<?= e(fv($def, 'decimales', 2)) ?>"></div>
                <div class="col-md-3"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="<?= e(fv($def, 'ordre', 0)) ?>"></div>
                <div class="col-12"><label class="form-label" for="formule">Formule *</label>
                    <textarea name="formule" id="formule" rows="3" class="form-control formule<?= invalide($err, 'formule') ?>"><?= e(fv($def, 'formule')) ?></textarea><?= erreur_champ($err, 'formule') ?>
                    <div class="form-text">Opérateurs : <code>+ - * / % ^ ( )</code>, comparaisons <code>&lt; &lt;= &gt; &gt;= == !=</code>, logique <code>et ou !</code>.
                        Fonctions : <code>min() max() abs() round(x,n) floor() ceil() sqrt() si(cond, a, b) coalesce(a, b) nz(x) pct(a, b)</code>.
                        Une division par zéro donne « N/D ». Cliquez sur une variable pour l'insérer.</div></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="actif" value="1" id="actif"<?= coche(fv($def, 'actif', 1)) ?>>
                    <label class="form-check-label" for="actif">Indice actif (inclus dans les calculs)</label></div></div>
            </div></div>
            <div class="card mb-3"><div class="card-header">Interprétation automatique</div><div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label">Sens favorable</label><select name="sens" class="form-select"><?= options(libelles('sens'), fv($def, 'sens')) ?></select></div>
                <div class="col-md-4"><label class="form-label">Seuil bas</label><input name="seuil_bas" type="number" step="any" class="form-control<?= invalide($err, 'seuil_bas') ?>" value="<?= e(fv($def, 'seuil_bas')) ?>"><?= erreur_champ($err, 'seuil_bas') ?></div>
                <div class="col-md-4"><label class="form-label">Seuil haut</label><input name="seuil_haut" type="number" step="any" class="form-control<?= invalide($err, 'seuil_haut') ?>" value="<?= e(fv($def, 'seuil_haut')) ?>"><?= erreur_champ($err, 'seuil_haut') ?></div>
                <div class="col-12 small text-muted">Sens « haut » : valeur ≥ seuil haut = <b>bon</b>, &lt; seuil bas = <b>faible</b>, sinon <b>moyen</b>. Sens « bas » : ≤ seuil bas = bon, &gt; seuil haut = faible.
                    Dans les textes : <code>{valeur}</code> <code>{unite}</code> <code>{seuil_bas}</code> <code>{seuil_haut}</code>.</div>
                <div class="col-12"><label class="form-label text-success">Texte « bon »</label><input name="texte_bon" class="form-control" value="<?= e(fv($def, 'texte_bon')) ?>"></div>
                <div class="col-12"><label class="form-label text-warning">Texte « moyen »</label><input name="texte_moyen" class="form-control" value="<?= e(fv($def, 'texte_moyen')) ?>"></div>
                <div class="col-12"><label class="form-label text-danger">Texte « faible »</label><input name="texte_faible" class="form-control" value="<?= e(fv($def, 'texte_faible')) ?>"></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Tester sur les données réelles</span>
                <div class="d-flex gap-1"><select id="anneeTest" class="form-select form-select-sm w-auto">
                    <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?><option value="0">Vie entière</option></select>
                    <button type="button" class="btn btn-sm btn-outline-success" id="btnTester"><i class="bi bi-play-fill"></i> Tester</button></div></div>
                <div class="card-body" id="resultatTest"><p class="small text-muted mb-0">Le test évalue la formule saisie, sans l'enregistrer, sur le troupeau entier ou sur 5 animaux.</p></div></div>
            <div class="card mb-3"><div class="card-header">Variables disponibles</div><div class="card-body" style="max-height:420px;overflow:auto">
                <?php foreach (['animal' => variables_animal(), 'troupeau' => variables_troupeau()] as $p => $vars): ?>
                    <div data-portee-vars="<?= $p ?>"><?php foreach ($vars as $k => $desc): ?>
                        <div class="mb-1"><span class="badge text-bg-light border badge-variable" data-var="<?= e($k) ?>"><?= e($k) ?></span> <span class="small text-muted"><?= e($desc) ?></span></div>
                    <?php endforeach; ?></div>
                <?php endforeach; ?>
            </div></div>
            <?php if ($historique): ?>
                <div class="card"><div class="card-header">Historique des formules</div><ul class="list-group list-group-flush small">
                    <?php foreach ($historique as $h): ?>
                        <li class="list-group-item"><div class="text-muted"><?= fmt_date($h['modifie_le'], true) ?> · <?= e($h['nom_complet'] ?? '—') ?></div>
                            <?php if ($h['ancienne_formule']): ?><del class="formule text-danger"><?= e($h['ancienne_formule']) ?></del><br><?php endif; ?>
                            <span class="formule text-success"><?= e($h['nouvelle_formule']) ?></span></li>
                    <?php endforeach; ?></ul></div>
            <?php endif; ?>
        </div>
    </div>
    <button class="btn btn-foret"><i class="bi bi-check2 me-1"></i>Enregistrer l'indice</button>
</form>
<?php require INC . '/pied.php';
