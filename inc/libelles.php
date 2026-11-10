<?php
defined('INC') || exit; // fichier inclus uniquement
/** Libellés d'affichage des valeurs ENUM de la base. */
return [
    'espece'  => ['caprin' => 'Caprin', 'ovin' => 'Ovin'],
    'sexe'    => ['M' => 'Mâle', 'F' => 'Femelle'],
    'statut'  => ['actif' => 'Actif', 'vendu' => 'Vendu', 'mort' => 'Mort', 'reforme' => 'Réformé', 'perdu' => 'Perdu'],
    'origine' => ['naissance' => 'Né sur l\'exploitation', 'achat' => 'Acheté', 'don' => 'Don', 'autre' => 'Autre'],
    'type_naissance' => ['simple' => 'Simple', 'double' => 'Double', 'triple' => 'Triple', 'quadruple+' => 'Quadruple et +'],
    'contexte_pesee' => ['routine' => 'Routine', 'naissance' => 'Naissance', 'sevrage' => 'Sevrage', 'vente' => 'Vente', 'autre' => 'Autre'],
    'type_soin' => [
        'vaccination' => 'Vaccination', 'vermifuge' => 'Vermifuge / déparasitage', 'traitement' => 'Traitement',
        'maladie' => 'Maladie', 'blessure' => 'Blessure', 'visite' => 'Visite vétérinaire', 'autre' => 'Autre',
    ],
    'evolution_soin' => ['na' => '—', 'en_cours' => 'En cours', 'gueri' => 'Guéri', 'chronique' => 'Chronique', 'mort' => 'Mort'],
    'methode_saillie' => ['monte_naturelle' => 'Monte naturelle (en main)', 'insemination' => 'Insémination artificielle', 'lutte_libre' => 'Lutte libre'],
    'diagnostic_saillie' => ['non_fait' => 'Non fait', 'gestante' => 'Gestante', 'vide' => 'Vide', 'douteux' => 'Douteux'],
    'deroulement' => ['facile' => 'Facile', 'aidee' => 'Aidée', 'difficile' => 'Difficile', 'cesarienne' => 'Césarienne'],
    'type_mouvement' => [
        'changement_lot' => 'Changement de lot', 'transhumance' => 'Transhumance', 'pret' => 'Prêt', 'retour' => 'Retour', 'autre' => 'Autre',
    ],
    'portee' => ['animal' => 'Par animal', 'troupeau' => 'Troupeau (année / groupe)'],
    'type_groupe' => [
        'animal' => 'Animal', 'troupeau' => 'Troupeau entier', 'espece' => 'Espèce',
        'categorie' => 'Catégorie', 'race' => 'Race', 'lot' => 'Lot',
    ],
    'sens' => ['haut' => 'Plus élevé = meilleur', 'bas' => 'Plus bas = meilleur'],
    'niveau' => ['bon' => 'Bon', 'moyen' => 'Moyen', 'faible' => 'Faible', 'nd' => 'Non déterminé'],
    'type_evenement' => [
        'creation' => 'Création', 'modification' => 'Modification', 'suppression' => 'Suppression', 'sortie' => 'Sortie',
        'pesee' => 'Pesée', 'soin' => 'Santé', 'saillie' => 'Saillie', 'mise_bas' => 'Mise bas',
        'naissance' => 'Naissance', 'lait' => 'Production laitière', 'mouvement' => 'Mouvement', 'indice' => 'Indice',
    ],
    'action_trace' => [
        'ajout' => 'Ajout', 'modification' => 'Modification', 'suppression' => 'Suppression', 'connexion' => 'Connexion',
        'deconnexion' => 'Déconnexion', 'echec_connexion' => 'Échec de connexion', 'export' => 'Export PDF',
        'calcul' => 'Calcul d\'indices', 'acces_refuse' => 'Accès refusé', 'parametres' => 'Paramètres', 'cache' => 'Cache',
    ],
];
