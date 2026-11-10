<?php
/** Test AJAX d'une formule (non enregistrée) sur les données réelles. Réponse JSON. */
require __DIR__ . '/inc/init.php';
require INC . '/indices.php';
exiger_permission('indices.formules');
exiger_post();

$portee = ($_POST['portee'] ?? '') === 'animal' ? 'animal' : 'troupeau';
$formule = (string) ($_POST['formule'] ?? '');
$erreur = (new MoteurFormule())->valider($formule, array_keys(variables_portee($portee)));
if ($erreur) {
    json_reponse(['ok' => false, 'erreur' => $erreur]);
}
$def = [
    'portee' => $portee, 'formule' => $formule, 'decimales' => (int) ($_POST['decimales'] ?? 2), 'unite' => (string) ($_POST['unite'] ?? ''),
    'sens' => ($_POST['sens'] ?? 'haut') === 'bas' ? 'bas' : 'haut',
    'seuil_bas' => is_numeric($_POST['seuil_bas'] ?? '') ? $_POST['seuil_bas'] : null,
    'seuil_haut' => is_numeric($_POST['seuil_haut'] ?? '') ? $_POST['seuil_haut'] : null,
    'texte_bon' => $_POST['texte_bon'] ?? '', 'texte_moyen' => $_POST['texte_moyen'] ?? '', 'texte_faible' => $_POST['texte_faible'] ?? '',
];
$annee = (int) ($_POST['annee'] ?? date('Y'));
json_reponse(['ok' => true, 'annee' => $annee, 'resultats' => array_map(static fn($r) => [
    'libelle' => $r['libelle'],
    'valeur' => $r['valeur'] === null ? 'N/D' : number_format($r['valeur'], $def['decimales'], ',', ' ') . ' ' . $def['unite'],
    'niveau' => $r['interp'][0], 'texte' => $r['interp'][1],
], apercu_formule($def, $annee))]);
