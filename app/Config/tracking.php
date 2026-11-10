<?php
/**
 * Définition déclarative des modules de suivi.
 * Un seul contrôleur (TrackingController) gère liste, recherche, pagination
 * keyset, ajout, modification, suppression et exports PDF pour chacun.
 *
 * Types de champs : animal (saisie par n° de boucle), date, number, text,
 * textarea, select, lot, bool, virtual (non stocké).
 */
return [
    'weighings' => [
        'table' => 'weighings', 'title' => 'Pesées', 'singular' => 'pesée', 'icon' => 'bi-speedometer2',
        'history_type' => 'pesee',
        'fields' => [
            'animal_id'  => ['label' => 'Animal', 'type' => 'animal', 'required' => true],
            'event_date' => ['label' => 'Date de pesée', 'type' => 'date', 'required' => true],
            'weight'     => ['label' => 'Poids (kg)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 300, 'required' => true],
            'body_score' => ['label' => 'Note d\'état corporel (1-5)', 'type' => 'number', 'step' => '0.5', 'min' => 1, 'max' => 5],
            'context'    => ['label' => 'Contexte', 'type' => 'select', 'options' => 'weigh_context', 'default' => 'routine'],
            'notes'      => ['label' => 'Remarques', 'type' => 'text', 'maxlen' => 255],
        ],
        'list'    => ['event_date', 'animal_id', 'weight', 'body_score', 'context', 'notes'],
        'filters' => ['context'],
        'summary' => static fn(array $r) => sprintf('Pesée : %s kg%s', fmt_num($r['weight']), $r['body_score'] ? ' (NEC ' . fmt_num($r['body_score'], 1) . ')' : ''),
    ],

    'health' => [
        'table' => 'health_events', 'title' => 'Santé', 'singular' => 'intervention sanitaire', 'icon' => 'bi-heart-pulse',
        'history_type' => 'sante',
        'fields' => [
            'animal_id'       => ['label' => 'Animal', 'type' => 'animal', 'required' => true],
            'event_date'      => ['label' => 'Date', 'type' => 'date', 'required' => true],
            'event_type'      => ['label' => 'Type', 'type' => 'select', 'options' => 'health_type', 'required' => true],
            'diagnosis'       => ['label' => 'Diagnostic / motif', 'type' => 'text', 'maxlen' => 160],
            'product'         => ['label' => 'Produit / vaccin', 'type' => 'text', 'maxlen' => 120],
            'dose'            => ['label' => 'Dose', 'type' => 'text', 'maxlen' => 60],
            'veterinarian'    => ['label' => 'Vétérinaire / intervenant', 'type' => 'text', 'maxlen' => 120],
            'cost'            => ['label' => 'Coût', 'type' => 'number', 'step' => '0.01', 'min' => 0],
            'withdrawal_days' => ['label' => 'Délai d\'attente (jours)', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 365],
            'next_date'       => ['label' => 'Date de rappel', 'type' => 'date'],
            'outcome'         => ['label' => 'Évolution', 'type' => 'select', 'options' => 'health_outcome', 'default' => 'na'],
            'notes'           => ['label' => 'Remarques', 'type' => 'textarea', 'maxlen' => 500],
        ],
        'list'    => ['event_date', 'animal_id', 'event_type', 'diagnosis', 'product', 'cost', 'next_date', 'outcome'],
        'filters' => ['event_type', 'outcome'],
        'search'  => ['diagnosis', 'product', 'veterinarian'],
        'summary' => static fn(array $r) => trim(label('health_type', $r['event_type']) . ' : ' . ($r['diagnosis'] ?: '') . ($r['product'] ? ' — ' . $r['product'] : ''), ' :'),
    ],

    'matings' => [
        'table' => 'matings', 'title' => 'Saillies / luttes', 'singular' => 'saillie', 'icon' => 'bi-gender-ambiguous',
        'history_type' => 'saillie', 'animal_sex' => 'F',
        'fields' => [
            'animal_id'      => ['label' => 'Femelle', 'type' => 'animal', 'sex' => 'F', 'required' => true],
            'male_id'        => ['label' => 'Mâle (bouc / bélier)', 'type' => 'animal', 'sex' => 'M'],
            'event_date'     => ['label' => 'Date de saillie', 'type' => 'date', 'required' => true],
            'method'         => ['label' => 'Méthode', 'type' => 'select', 'options' => 'mating_method', 'default' => 'monte_naturelle'],
            'diagnosis'      => ['label' => 'Diagnostic de gestation', 'type' => 'select', 'options' => 'mating_diag', 'default' => 'non_fait'],
            'diagnosis_date' => ['label' => 'Date du diagnostic', 'type' => 'date'],
            'expected_birth' => ['label' => 'Mise bas prévue (auto si vide)', 'type' => 'date'],
            'notes'          => ['label' => 'Remarques', 'type' => 'text', 'maxlen' => 255],
        ],
        'list'    => ['event_date', 'animal_id', 'male_id', 'method', 'diagnosis', 'expected_birth'],
        'filters' => ['method', 'diagnosis'],
        'summary' => static fn(array $r) => 'Saillie (' . label('mating_method', $r['method']) . ')' . ($r['male_tag'] ?? '' ? ' avec ' . $r['male_tag'] : '') . ' — diagnostic : ' . label('mating_diag', $r['diagnosis']),
    ],

    'births' => [
        'table' => 'births', 'title' => 'Mises bas', 'singular' => 'mise bas', 'icon' => 'bi-stars',
        'history_type' => 'mise_bas', 'animal_sex' => 'F',
        'fields' => [
            'animal_id'    => ['label' => 'Mère', 'type' => 'animal', 'sex' => 'F', 'required' => true],
            'father_id'    => ['label' => 'Père', 'type' => 'animal', 'sex' => 'M'],
            'event_date'   => ['label' => 'Date de mise bas', 'type' => 'date', 'required' => true],
            'born_total'   => ['label' => 'Nés (total)', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 8, 'required' => true, 'default' => 1],
            'born_alive'   => ['label' => 'Nés vivants', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 8, 'required' => true, 'default' => 1],
            'born_males'   => ['label' => 'dont mâles', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 8, 'default' => 0],
            'born_females' => ['label' => 'dont femelles', 'type' => 'number', 'step' => '1', 'min' => 0, 'max' => 8, 'default' => 0],
            'ease'         => ['label' => 'Déroulement', 'type' => 'select', 'options' => 'birth_ease', 'default' => 'facile'],
            'abortion'     => ['label' => 'Avortement', 'type' => 'bool'],
            'notes'        => ['label' => 'Remarques', 'type' => 'text', 'maxlen' => 255],
            'create_offspring' => ['label' => 'Créer automatiquement les fiches des nouveau-nés vivants', 'type' => 'virtual', 'only_create' => true],
        ],
        'list'    => ['event_date', 'animal_id', 'father_id', 'born_total', 'born_alive', 'ease', 'abortion'],
        'filters' => ['ease'],
        'summary' => static fn(array $r) => (int) $r['abortion']
            ? 'Avortement'
            : sprintf('Mise bas : %d né(s) dont %d vivant(s) (%d M / %d F)', $r['born_total'], $r['born_alive'], $r['born_males'], $r['born_females']),
    ],

    'milk' => [
        'table' => 'milk_records', 'title' => 'Production laitière', 'singular' => 'contrôle laitier', 'icon' => 'bi-droplet',
        'history_type' => 'lait', 'animal_sex' => 'F',
        'fields' => [
            'animal_id'    => ['label' => 'Femelle', 'type' => 'animal', 'sex' => 'F', 'required' => true],
            'event_date'   => ['label' => 'Date du contrôle', 'type' => 'date', 'required' => true],
            'quantity'     => ['label' => 'Quantité (L / jour)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 20, 'required' => true],
            'fat_rate'     => ['label' => 'Taux butyreux (g/L)', 'type' => 'number', 'step' => '0.1', 'min' => 0, 'max' => 200],
            'protein_rate' => ['label' => 'Taux protéique (g/L)', 'type' => 'number', 'step' => '0.1', 'min' => 0, 'max' => 200],
            'notes'        => ['label' => 'Remarques', 'type' => 'text', 'maxlen' => 255],
        ],
        'list'    => ['event_date', 'animal_id', 'quantity', 'fat_rate', 'protein_rate', 'notes'],
        'filters' => [],
        'summary' => static fn(array $r) => sprintf('Contrôle laitier : %s L/j', fmt_num($r['quantity'])),
    ],

    'movements' => [
        'table' => 'movements', 'title' => 'Mouvements', 'singular' => 'mouvement', 'icon' => 'bi-arrow-left-right',
        'history_type' => 'mouvement',
        'fields' => [
            'animal_id'     => ['label' => 'Animal', 'type' => 'animal', 'required' => true],
            'event_date'    => ['label' => 'Date', 'type' => 'date', 'required' => true],
            'movement_type' => ['label' => 'Type', 'type' => 'select', 'options' => 'movement_type', 'default' => 'changement_lot'],
            'from_lot_id'   => ['label' => 'Lot de départ (auto si vide)', 'type' => 'lot'],
            'to_lot_id'     => ['label' => 'Lot d\'arrivée', 'type' => 'lot'],
            'notes'         => ['label' => 'Remarques', 'type' => 'text', 'maxlen' => 255],
        ],
        'list'    => ['event_date', 'animal_id', 'movement_type', 'from_lot_id', 'to_lot_id', 'notes'],
        'filters' => ['movement_type'],
        'summary' => static fn(array $r) => label('movement_type', $r['movement_type'])
            . (($r['from_lot_name'] ?? null) || ($r['to_lot_name'] ?? null) ? ' : ' . ($r['from_lot_name'] ?? '—') . ' → ' . ($r['to_lot_name'] ?? '—') : ''),
    ],
];
