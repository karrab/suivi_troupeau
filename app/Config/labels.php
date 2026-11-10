<?php
/** Libellés des valeurs ENUM (affichage). */
return [
    'species' => ['caprin' => 'Caprin', 'ovin' => 'Ovin'],
    'sex'     => ['M' => 'Mâle', 'F' => 'Femelle'],
    'status'  => ['actif' => 'Actif', 'vendu' => 'Vendu', 'mort' => 'Mort', 'reforme' => 'Réformé', 'perdu' => 'Perdu'],
    'origin'  => ['naissance' => 'Né sur l\'exploitation', 'achat' => 'Acheté', 'don' => 'Don', 'autre' => 'Autre'],
    'birth_type' => ['simple' => 'Simple', 'double' => 'Double', 'triple' => 'Triple', 'quadruple+' => 'Quadruple et +'],
    'weigh_context' => ['routine' => 'Routine', 'naissance' => 'Naissance', 'sevrage' => 'Sevrage', 'vente' => 'Vente', 'autre' => 'Autre'],
    'health_type' => [
        'vaccination' => 'Vaccination', 'vermifuge' => 'Vermifuge / déparasitage', 'traitement' => 'Traitement',
        'maladie' => 'Maladie', 'blessure' => 'Blessure', 'visite' => 'Visite vétérinaire', 'autre' => 'Autre',
    ],
    'health_outcome' => ['na' => '—', 'en_cours' => 'En cours', 'gueri' => 'Guéri', 'chronique' => 'Chronique', 'mort' => 'Mort'],
    'mating_method' => ['monte_naturelle' => 'Monte naturelle (en main)', 'insemination' => 'Insémination artificielle', 'lutte_libre' => 'Lutte libre'],
    'mating_diag' => ['non_fait' => 'Non fait', 'gestante' => 'Gestante', 'vide' => 'Vide', 'douteux' => 'Douteux'],
    'birth_ease' => ['facile' => 'Facile', 'aidee' => 'Aidée', 'difficile' => 'Difficile', 'cesarienne' => 'Césarienne'],
    'movement_type' => [
        'changement_lot' => 'Changement de lot', 'transhumance' => 'Transhumance', 'pret' => 'Prêt',
        'retour' => 'Retour', 'autre' => 'Autre',
    ],
    'scope' => ['animal' => 'Par animal', 'troupeau' => 'Troupeau (année / groupe)'],
    'group_type' => [
        'animal' => 'Animal', 'global' => 'Troupeau entier', 'espece' => 'Espèce',
        'categorie' => 'Catégorie', 'race' => 'Race', 'lot' => 'Lot',
    ],
    'better' => ['haut' => 'Plus élevé = meilleur', 'bas' => 'Plus bas = meilleur'],
    'level' => ['bon' => 'Bon', 'moyen' => 'Moyen', 'faible' => 'Faible', 'na' => 'Non déterminé'],
    'history_type' => [
        'creation' => 'Création', 'modification' => 'Modification', 'suppression' => 'Suppression', 'sortie' => 'Sortie',
        'pesee' => 'Pesée', 'sante' => 'Santé', 'saillie' => 'Saillie', 'mise_bas' => 'Mise bas',
        'naissance' => 'Naissance', 'lait' => 'Production laitière', 'mouvement' => 'Mouvement', 'indice' => 'Indice',
    ],
    'audit_action' => [
        'create' => 'Création', 'update' => 'Modification', 'delete' => 'Suppression', 'login' => 'Connexion',
        'logout' => 'Déconnexion', 'login_failed' => 'Échec connexion', 'export' => 'Export PDF',
        'compute' => 'Calcul d\'indices', 'forbidden' => 'Accès refusé', 'settings' => 'Paramètres', 'cache' => 'Cache',
    ],
];
