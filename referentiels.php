<?php
/** Référentiels : races (?type=races) et lots (?type=lots) — liste, ajout, modification, suppression. */
require __DIR__ . '/inc/init.php';
exiger_permission('referentiels.voir');

$TYPES = [
    'races' => ['titre' => 'Races', 'singulier' => 'race', 'cle' => 'race_id',
        'champs' => ['nom' => ['Nom', 'obligatoire|maxcar:80'], 'espece' => ['Espèce', 'obligatoire|dans:caprin,ovin'], 'description' => ['Description', 'maxcar:255']]],
    'lots' => ['titre' => 'Lots / parcs', 'singulier' => 'lot', 'cle' => 'lot_id',
        'champs' => ['nom' => ['Nom', 'obligatoire|maxcar:80'], 'emplacement' => ['Emplacement', 'maxcar:120'], 'description' => ['Description', 'maxcar:255']]],
];
$type = (string) ($_POST['type'] ?? $_GET['type'] ?? 'races');
$cfg = $TYPES[$type] ?? abandonner(404);

if (est_post()) {
    exiger_permission('referentiels.gerer');
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'supprimer') {
        $ancien = bd_trouver($type, $id) ?? abandonner(404);
        bd_supprimer($type, $id); // clé étrangère ON DELETE SET NULL sur animaux
        tracer('suppression', $type, $id, ucfirst($cfg['singulier']) . ' ' . $ancien['nom'], $ancien, null);
        cache_invalider('referentiels', 'animaux');
        flash('success', ucfirst($cfg['singulier']) . ' supprimé(e).');
        rediriger(url('referentiels.php', ['type' => $type]));
    }
    $regles = array_map(static fn($c) => $c[1], $cfg['champs']);
    $libs = array_map(static fn($c) => $c[0], $cfg['champs']);
    [$d, $err] = valider($_POST, $regles, $libs);
    $doublon = bd_valeur("SELECT id FROM `$type` WHERE nom = :n" . ($type === 'races' ? ' AND espece = :e' : ''), ['n' => (string) $d['nom'], 'e' => (string) ($d['espece'] ?? '')]);
    if ($doublon && (int) $doublon !== $id) {
        $err['nom'] = 'Ce nom existe déjà.';
    }
    if ($err) {
        garder_saisie($_POST, $err);
        rediriger(url('referentiels.php', ['type' => $type, 'modifier' => $id ?: null]));
    }
    if ($id) {
        $ancien = bd_trouver($type, $id) ?? abandonner(404);
        bd_modifier($type, $d, $id);
        tracer('modification', $type, $id, ucfirst($cfg['singulier']) . ' ' . $d['nom'], $ancien, $d);
    } else {
        $id = bd_inserer($type, $d);
        tracer('ajout', $type, $id, ucfirst($cfg['singulier']) . ' ' . $d['nom'], null, $d);
    }
    cache_invalider('referentiels');
    flash('success', ucfirst($cfg['singulier']) . ' enregistré(e).');
    rediriger(url('referentiels.php', ['type' => $type]));
}

$lignes = bd_tous("SELECT x.*, (SELECT COUNT(*) FROM animaux a WHERE a.{$cfg['cle']} = x.id AND a.statut = 'actif') AS nb FROM `$type` x ORDER BY x.nom");
$edition = entier_get('modifier') ? bd_trouver($type, entier_get('modifier')) : null;
$obj = $edition ?? [];
$err = erreurs_form();
$titre = $cfg['titre'];
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-tags me-2"></i>Référentiels</h1>
    <button class="btn btn-outline-secondary btn-sm d-print-none" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button>
</div>
<ul class="nav nav-pills mb-3 d-print-none">
    <?php foreach ($TYPES as $k => $t): ?><li class="nav-item"><a class="nav-link<?= $k === $type ? ' active bg-foret' : '' ?>" href="<?= e(url('referentiels.php', ['type' => $k])) ?>"><?= e($t['titre']) ?></a></li><?php endforeach; ?>
</ul>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><table class="table table-sm table-hover mb-0">
        <thead><tr><?php foreach ($cfg['champs'] as [$l]): ?><th><?= e($l) ?></th><?php endforeach; ?><th class="text-end">Animaux actifs</th><th class="actions d-print-none"></th></tr></thead>
        <tbody><?php foreach ($lignes as $r): ?>
            <tr><?php foreach ($cfg['champs'] as $c => $_): ?><td><?= e($c === 'espece' ? libelle('espece', $r[$c]) : $r[$c]) ?></td><?php endforeach; ?>
                <td class="text-end"><a href="<?= e(url('animaux.php', [$cfg['cle'] => $r['id']])) ?>"><?= (int) $r['nb'] ?></a></td>
                <td class="actions d-print-none"><?php if (peut('referentiels.gerer')): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('referentiels.php', ['type' => $type, 'modifier' => $r['id']])) ?>"><i class="bi bi-pencil"></i></a>
                    <?= bouton_supprimer('referentiels.php', (int) $r['id'], 'Supprimer « ' . $r['nom'] . ' » ? Les animaux concernés n\'auront plus de ' . $cfg['singulier'] . '.', ['type' => $type]) ?>
                <?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div></div>
    <?php if (peut('referentiels.gerer')): ?>
        <div class="col-lg-4 d-print-none"><div class="card"><div class="card-header"><?= $edition ? 'Modifier' : 'Ajouter' ?> : <?= e($cfg['singulier']) ?></div><div class="card-body">
            <form method="post" action="referentiels.php"><?= champ_csrf() ?><input type="hidden" name="type" value="<?= e($type) ?>">
                <?php if ($edition): ?><input type="hidden" name="id" value="<?= (int) $edition['id'] ?>"><?php endif; ?>
                <?php foreach ($cfg['champs'] as $c => [$l, $r]): ?>
                    <div class="mb-2"><label class="form-label small"><?= e($l) ?><?= str_contains($r, 'obligatoire') ? ' *' : '' ?></label>
                        <?php if ($c === 'espece'): ?><select name="espece" class="form-select form-select-sm"><?= options(libelles('espece'), fv($obj, $c)) ?></select>
                        <?php else: ?><input name="<?= $c ?>" class="form-control form-control-sm<?= invalide($err, $c) ?>" value="<?= e(fv($obj, $c)) ?>"><?php endif; ?>
                        <?= erreur_champ($err, $c) ?></div>
                <?php endforeach; ?>
                <button class="btn btn-foret btn-sm"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
                <?php if ($edition): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('referentiels.php', ['type' => $type])) ?>">Annuler</a><?php endif; ?>
            </form>
        </div></div></div>
    <?php endif; ?>
</div>
<?php require INC . '/pied.php';
