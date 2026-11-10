# Suivi Troupeau — système d'information caprin & ovin

Application web de suivi de troupeau (chèvres, boucs, chevreaux, chevrettes, brebis, béliers, moutons, agneaux, agnelles), écrite en **PHP natif procédural** : **une page `.php` par écran**, sans framework, sans contrôleurs, sans dossier de vues, sans Composer. Base **MySQL 8 / MariaDB 10.6+ en InnoDB**, avec des **tables et des colonnes en français**, et une interface **Bootstrap 5**.

## Principe

Chaque page commence de la même façon :

```php
<?php
require __DIR__ . '/inc/init.php';        // configuration, session, BD, cache, CSRF
exiger_permission('animaux.voir');         // contrôle d'accès

if (est_post()) { /* traitement du formulaire, puis rediriger() */ }

$lignes = bd_tous('SELECT ... FROM animaux WHERE ...', [...]);   // requêtes préparées

$titre = 'Animaux';
require INC . '/entete.php';               // en-tête HTML + menu
?>
  ... HTML de la page ...
<?php require INC . '/pied.php';
```

Le dossier `inc/` ne contient que des **fonctions** réutilisables, plus deux bibliothèques autonomes (moteur de formules et générateur PDF) :

| Fichier | Rôle |
|---|---|
| `init.php` | Chargement commun, en-têtes de sécurité, session, vérification CSRF des POST |
| `config.php` | Configuration (surchargée par `config.local.php`) |
| `bd.php` | `bd_tous()`, `bd_un()`, `bd_valeur()`, `bd_inserer()`, `bd_modifier()`, `bd_transaction()`… (PDO, requêtes préparées natives) |
| `cache.php` | Cache **APCu** : `cache_memoriser()`, `cache_invalider()` (étiquettes versionnées) |
| `auth.php` | `connecter()`, `utilisateur()`, `peut()`, `exiger_permission()` |
| `traces.php` | `tracer()` (journal d'audit avant / après), `historique_ajouter()` (chronologie de l'animal) |
| `pagination.php` | Pagination **keyset** : `paginer()`, `compter()`, `tout_lire()` |
| `validation.php` | `valider($_POST, ['poids' => 'obligatoire\|nombre\|min:0'])` |
| `suivis.php` | Déclaration des 6 suivis (champs, colonnes, filtres, résumé) |
| `indices.php` | Calcul, interprétation et enregistrement des indices |
| `formule.php` | Moteur de formules sécurisé (sans `eval`) |
| `pdf.php` | Générateur PDF natif (tableaux, fiches, en-tête et pied de page) |
| `fonctions.php`, `html.php`, `libelles.php`, `referentiels.php` | Formats, composants HTML, libellés, référentiels |
| `entete.php`, `pied.php` | Mise en page commune |

## Pages

| Page | Contenu |
|---|---|
| `index.php` | Tableau de bord : effectifs, graphiques, mises bas prévues, rappels sanitaires, délais d'attente |
| `animaux.php` | Liste des animaux, **recherche avancée**, tri, keyset, imprimer, **export PDF**, supprimer, reclasser les jeunes |
| `animal.php` | **Fiche animal** : identité, généalogie, courbe de poids, tous les suivis, descendance, indices, **historique** ; PDF ; calcul des indices |
| `animal_form.php` | Ajout / modification d'un animal |
| `suivis.php?type=…` | Liste d'un suivi : `pesees`, `soins`, `saillies`, `mises_bas`, `lait`, `mouvements` (filtres, PDF, suppression) |
| `suivi_form.php`, `suivi_pdf.php` | Saisie / modification d'un suivi ; PDF d'un enregistrement |
| `indices.php` | Calcul et synthèse des indices par **année** et par **troupeau / espèce / catégorie / race / lot** |
| `indices_resultats.php`, `indice_detail.php`, `indices_evolution.php` | Résultats enregistrés, détail (formule et variables), évolution pluriannuelle |
| `formules.php`, `formule_form.php`, `formule_tester.php` | **Modification des formules** des indices, seuils, textes d'interprétation ; test en direct |
| `utilisateurs.php`, `utilisateur_form.php` | Gestion des utilisateurs |
| `roles.php`, `role_form.php` | Rôles et **matrice des permissions** |
| `traces.php`, `trace.php` | **Journal des traces** (valeurs avant / après) |
| `referentiels.php`, `parametres.php`, `systeme.php` | Races et lots, paramètres, état d'OPcache, d'APCu et des tables |

Chaque liste propose les boutons **Modifier**, **Supprimer** (avec confirmation), **Imprimer** et **Exporter PDF** (l'export reprend les filtres en cours).

## Base de données (en français)

`animaux`, `categories`, `races`, `lots`, `historique_animaux`, `pesees`, `soins`, `saillies`, `mises_bas`, `controles_laitiers`, `mouvements`, `indices`, `historique_formules`, `resultats_indices`, `utilisateurs`, `roles`, `permissions`, `roles_permissions`, `traces`, `parametres`.

Toutes les tables sont en **InnoDB**, avec des clés étrangères (suppressions en cascade ou remise à NULL) et des index composites `(filtre, colonne_de_tri, id)` adaptés à la pagination keyset. Le schéma complet est dans `sql/schema.sql`.

## Performance

- **OPcache** : code PHP compilé en mémoire, JIT et préchargement (`config/php-performance.ini`, `config/prechargement.php`).
- **APCu** : paramètres, permissions, référentiels, statistiques du tableau de bord, totaux des listes et synthèses d'indices. L'invalidation est automatique : par exemple `cache_invalider('animaux')` après chaque modification.
- **Keyset** : `WHERE (tri > :v OR (tri = :v AND id > :id)) ORDER BY tri, id LIMIT n+1` à la place de `OFFSET`, pour un coût constant quelle que soit la page (`EXPLAIN` : *range* sur l'index).
- **Indices calculés en masse** : une requête agrégée par famille de données (environ 570 animaux × 10 indices en 0,2 s), puis `INSERT … ON DUPLICATE KEY UPDATE` par lots.

## Installation

Prérequis : PHP 8.1+ avec `pdo_mysql`, `mbstring`, `iconv` et `zlib` (recommandé : `opcache` et `apcu`), MySQL 8 ou MariaDB 10.6+.

1. Créer la base et l'utilisateur MySQL :

   ```sql
   CREATE DATABASE suivi_troupeau CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'troupeau'@'localhost' IDENTIFIED BY 'mot-de-passe-fort';
   GRANT ALL ON suivi_troupeau.* TO 'troupeau'@'localhost';
   ```

2. Copier `inc/config.local.php.exemple` en `inc/config.local.php` et y renseigner l'accès à la base.

3. Créer les tables, les données de référence, les rôles et l'administrateur :

   ```bash
   php outils/installer.php --neuf --mdp-admin='VotreMotDePasse'
   ```

   Ajouter `--demo` pour générer un troupeau de démonstration sur 3 ans.

4. Serveur web :
   - **Apache / XAMPP / WAMP** : copier le dossier dans la racine web (par exemple `htdocs/suivi_troupeau`). Les fichiers `.htaccess` bloquent l'accès à `inc/`, `sql/`, `outils/`, `config/` et `stockage/`. Aucun réglage d'URL n'est nécessaire : tous les liens sont relatifs.
   - **Nginx** : interdire ces mêmes dossiers (`location ~ ^/(inc|sql|outils|config|stockage)/ { deny all; }`).
   - **Test rapide** :

     ```bash
     php -S 127.0.0.1:8080 outils/routeur.php
     ```

5. Production : appliquer `config/php-performance.ini` (OPcache + APCu) et `config/mysql.cnf`.

## Rôles par défaut

| Rôle | Droits |
|---|---|
| Administrateur | Accès complet |
| Gestionnaire | Animaux et suivis (tous droits), calcul et export des indices, référentiels, consultation des traces |
| Vétérinaire | Consultation, santé (tous droits), saisie des saillies, des mises bas et des pesées, exports |
| Consultant | Consultation et exports uniquement (sans l'administration) |

## Formules des indices

Le moteur accepte :

- les opérateurs `+ - * / % ^` ;
- les comparaisons `< <= > >= == !=` ;
- la logique `et`, `ou`, `!` ;
- les fonctions `min`, `max`, `abs`, `round(x, n)`, `floor`, `ceil`, `sqrt`, `si(condition, a, b)`, `coalesce`, `nz`, `pct(a, b)`.

Une division par zéro donne « N/D ». Exemples :

| Indice | Formule |
|---|---|
| Taux de fertilité | `femelles_mises_bas / femelles_reproductrices * 100` |
| Taux de prolificité | `nes_total / mises_bas * 100` |
| Mortalité des jeunes | `morts_jeunes / nes_vivants * 100` |
| GMQ par animal (g/j) | `(poids_fin - poids_debut) / jours_pesee * 1000` |

Chaque résultat conserve la formule appliquée et la valeur des variables utilisées. Chaque modification de formule est historisée.

## Sécurité

- Requêtes préparées et jeton CSRF sur tous les POST.
- Échappement systématique des sorties.
- Mots de passe hachés avec `password_hash`, session régénérée à la connexion, compte bloqué après 5 échecs de connexion.
- CSP stricte (aucun script inline, toutes les ressources servies localement).
- Chaque fichier de `inc/` refuse d'être appelé directement.
