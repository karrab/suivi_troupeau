<?php
/** Paramètres généraux et zootechniques (table parametres, mis en cache APCu). */
require __DIR__ . '/inc/init.php';
exiger_permission('parametres.gerer');

if (est_post()) {
    $avant = $apres = $err = [];
    foreach (bd_tous('SELECT * FROM parametres') as $p) {
        $k = $p['cle'];
        if (!array_key_exists($k, $_POST['p'] ?? [])) {
            continue;
        }
        $v = trim((string) $_POST['p'][$k]);
        if (in_array($p['type'], ['entier', 'nombre'], true) && !is_numeric($v)) {
            $err[$k] = "« {$p['libelle']} » doit être numérique.";
            continue;
        }
        if ($p['type'] === 'entier') {
            $v = (string) max(0, (int) $v);
        }
        if ((string) $p['valeur'] !== $v) {
            $avant[$k] = $p['valeur'];
            $apres[$k] = $v;
            bd_requete('UPDATE parametres SET valeur = :v WHERE cle = :k', ['v' => mb_substr($v, 0, 1000), 'k' => $k]);
        }
    }
    cache_invalider('parametres');
    if ($apres) {
        tracer('parametres', 'parametres', null, 'Modification des paramètres : ' . implode(', ', array_keys($apres)), $avant, $apres);
    }
    $err ? garder_saisie([], $err) : null;
    flash($err ? 'warning' : 'success', $err ? 'Certains paramètres n\'ont pas été enregistrés.' : 'Paramètres enregistrés.');
    rediriger('parametres.php');
}

$groupes = [];
foreach (bd_tous('SELECT * FROM parametres ORDER BY groupe, ordre') as $p) {
    $groupes[$p['groupe']][] = $p;
}
$err = erreurs_form();
$noms = ['general' => 'Exploitation', 'zootechnie' => 'Paramètres zootechniques (utilisés par les indices)', 'alertes' => 'Alertes du tableau de bord', 'affichage' => 'Affichage'];
$titre = 'Paramètres';
require INC . '/entete.php';
?>
<h1 class="h4 titre-page mb-3"><i class="bi bi-gear me-2"></i>Paramètres</h1>
<form method="post" action="parametres.php"><?= champ_csrf() ?>
    <div class="row g-3">
        <?php foreach ($groupes as $g => $liste): ?>
            <div class="col-lg-6"><div class="card h-100"><div class="card-header"><?= e($noms[$g] ?? ucfirst($g)) ?></div><div class="card-body">
                <?php foreach ($liste as $p): ?>
                    <div class="mb-2"><label class="form-label small" for="p_<?= e($p['cle']) ?>"><?= e($p['libelle']) ?></label>
                        <input id="p_<?= e($p['cle']) ?>" name="p[<?= e($p['cle']) ?>]" class="form-control form-control-sm<?= invalide($err, $p['cle']) ?>"
                               type="<?= in_array($p['type'], ['entier', 'nombre'], true) ? 'number' : 'text' ?>"<?= $p['type'] === 'nombre' ? ' step="any"' : '' ?> value="<?= e($p['valeur']) ?>">
                        <?= erreur_champ($err, $p['cle']) ?></div>
                <?php endforeach; ?>
            </div></div></div>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-foret mt-3"><i class="bi bi-check2 me-1"></i>Enregistrer les paramètres</button>
    <a class="btn btn-outline-secondary mt-3" href="formules.php"><i class="bi bi-calculator me-1"></i>Formules des indices</a>
</form>
<?php require INC . '/pied.php';
