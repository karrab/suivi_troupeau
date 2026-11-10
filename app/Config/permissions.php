<?php
/**
 * Catalogue des permissions (module => libellé + actions).
 * Synchronisé en base par bin/install.php (et Paramètres > Système).
 */
$crud = ['view' => 'Consulter', 'create' => 'Ajouter', 'edit' => 'Modifier', 'delete' => 'Supprimer', 'export' => 'Imprimer / exporter PDF'];

return [
    'animals'    => ['label' => 'Animaux (fiches)', 'actions' => $crud],
    'weighings'  => ['label' => 'Suivi : pesées', 'actions' => $crud],
    'health'     => ['label' => 'Suivi : santé', 'actions' => $crud],
    'matings'    => ['label' => 'Suivi : saillies', 'actions' => $crud],
    'births'     => ['label' => 'Suivi : mises bas', 'actions' => $crud],
    'milk'       => ['label' => 'Suivi : production laitière', 'actions' => $crud],
    'movements'  => ['label' => 'Suivi : mouvements', 'actions' => $crud],
    'indices'    => ['label' => 'Indices zootechniques', 'actions' => [
        'view' => 'Consulter les résultats', 'compute' => 'Calculer / enregistrer', 'delete' => 'Supprimer des résultats',
        'export' => 'Imprimer / exporter PDF', 'formulas' => 'Modifier les formules',
    ]],
    'references' => ['label' => 'Référentiels (races, lots)', 'actions' => ['view' => 'Consulter', 'manage' => 'Gérer']],
    'users'      => ['label' => 'Utilisateurs', 'actions' => $crud],
    'roles'      => ['label' => 'Rôles & permissions', 'actions' => ['view' => 'Consulter', 'manage' => 'Gérer']],
    'audit'      => ['label' => 'Traces (journal)', 'actions' => ['view' => 'Consulter', 'export' => 'Exporter PDF']],
    'settings'   => ['label' => 'Paramètres', 'actions' => ['manage' => 'Gérer les paramètres', 'system' => 'Système & cache']],
];
