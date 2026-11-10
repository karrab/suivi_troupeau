<?php
/** PDF d'un enregistrement de suivi (fiche d'intervention). */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';

$type = (string) ($_GET['type'] ?? '');
$s = suivi($type);
exiger_permission("$type.exporter");
$l = suivi_charger($s, entier_get('id'));
tracer('export', $type, (int) $l['id'], 'PDF : ' . $s['singulier'] . ' n° ' . $l['id']);

$pdf = new PdfRapport(ucfirst($s['singulier']) . ' n° ' . $l['id'], 'Animal : ' . $l['animal_boucle'] . ($l['animal_nom'] ? ' — ' . $l['animal_nom'] : ''));
$pdf->ajouterPage();
$pdf->titreSection(ucfirst($s['singulier']));
$paires = [];
foreach ($s['champs'] as $c => $d) {
    if ($d['type'] !== 'virtuel') {
        $paires[$d['libelle']] = suivi_valeur($s, $c, $l, false) ?: '—';
    }
}
$pdf->clesValeurs($paires, 1);
$pdf->setFont(false, 8);
$pdf->paragraph('Saisi le ' . fmt_date($l['cree_le'], true) . ($l['modifie_le'] ? ' — modifié le ' . fmt_date($l['modifie_le'], true) : ''), 4.5);
$pdf->envoyer($type . '_' . $l['id'] . '.pdf');
