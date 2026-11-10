# Suivi Troupeau — système d'information caprin & ovin

Application web de suivi de troupeau (chèvres, boucs, chevreaux, chevrettes, brebis, béliers, moutons, agneaux, agnelles) en **PHP natif 8.1+**, **MySQL 8 / MariaDB 10.6+ (InnoDB)** et **Bootstrap 5**, sans framework ni dépendance Composer.

## Fonctionnalités

| Module | Contenu |
|---|---|
| **Fiche animal** | Identification (boucle, nom, catégorie → espèce et sexe, race, robe, lot), naissance (date, poids, type), généalogie (mère / père, descendance), entrée (naissance, achat, don), sortie (vente, mort, réforme, perte), courbe de poids, onglets de tous les suivis, historique chronologique, indices calculés. |
| **Suivis** | Pesées (NEC), santé (vaccins, vermifuges, traitements, maladies, coût, délai d'attente, rappel), saillies (diagnostic de gestation, date de mise bas prévue calculée), mises bas (création automatique des fiches des nouveau-nés), production laitière (TB / TP), mouvements (changement de lot automatique). |
| **Historique** | Chaque création, modification (avant → après), sortie, suivi ou suppression alimente la chronologie de l'animal (`animal_history`). |
| **Indices** | Calcul, **interprétation** (bon / moyen / faible + texte) et **enregistrement** par **année**, par **animal**, et par **groupe** (troupeau entier, espèce, catégorie, race, lot), ou sur la vie entière. Évolution pluriannuelle avec graphiques. |
| **Formules paramétrables** | *Formules des indices* : modifier la formule, l'unité, les seuils, le sens favorable et les textes d'interprétation, tester sur les données réelles avant d'enregistrer. Chaque modification de formule est versionnée. |
| **Recherche avancée** | Boucle / nom, espèce, catégorie, sexe, statut, race, lot, origine, âge min / max, dates de naissance / entrée / sortie, mère, père ; tri par colonnes. |
| **Actions** | Boutons **Modifier**, **Supprimer** (confirmation), **Imprimer** (feuille de style d'impression) et **PDF** sur chaque fiche ou enregistrement ; **export PDF de toutes les listes** avec les filtres en cours. |
| **Utilisateurs & permissions** | Utilisateurs, rôles, matrice de permissions par module et par action, rôle « accès complet », verrouillage après 5 échecs de connexion. |
| **Traces** | Journal d'audit : connexions, échecs, accès refusés, créations, modifications (valeurs avant / après), suppressions, exports, calculs, paramètres, avec IP et navigateur. |
| **Tableau de bord** | Effectifs, naissances / morts / ventes de l'année, graphiques, mises bas prévues, rappels sanitaires, délais d'attente en cours. |

## Performance

- **OPcache** : bytecode compilé en mémoire, JIT, préchargement optionnel (`config/php-performance.ini`, `config/preload.php`).
- **APCu** (`App\Core\Cache`) : paramètres, permissions des rôles, référentiels, statistiques du tableau de bord, totaux des listes, synthèses d'indices. L'invalidation passe par des **tags versionnés** : une modification incrémente la version du tag, ce qui rend obsolètes toutes ses clés en une opération. Sans APCu, l'application bascule sur un cache limité à la requête.
- **InnoDB** partout, avec clés étrangères, transactions et cascades. Index composites **(filtre, colonne de tri, id)** prévus pour chaque liste.
- **Pagination keyset** (`App\Core\KeysetPaginator`) : `WHERE (tri, id) > (:dernier_tri, :dernier_id) ORDER BY tri, id LIMIT n+1` au lieu de `OFFSET`, donc un coût constant quelle que soit la page (vérifié avec `EXPLAIN` : *range* sur l'index). Navigation Précédent / Suivant via un curseur encodé ; le total est mis en cache 60 s.
- Indices calculés **en masse** : une requête agrégée par famille de données, aucune requête par animal (environ 560 animaux × 10 indices en moins de 0,2 s), puis insertion par lots avec `INSERT … ON DUPLICATE KEY UPDATE`.

## Installation

Prérequis : PHP 8.1+ avec `pdo_mysql`, `mbstring`, `iconv` et `zlib` (recommandé : `opcache` et `apcu`), MySQL 8 ou MariaDB 10.6+.

1. Créer la base et l'utilisateur MySQL :

   ```sql
   CREATE DATABASE suivi_troupeau CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'troupeau'@'localhost' IDENTIFIED BY 'mot-de-passe-fort';
   GRANT ALL ON suivi_troupeau.* TO 'troupeau'@'localhost';
   ```

2. Configurer l'accès : copier `config/config.local.php.example` en `config/config.local.php` et renseigner les paramètres de la base (ou utiliser les variables d'environnement `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

3. Installer le schéma, les données de référence, les rôles et l'administrateur :

   ```bash
   php bin/install.php --fresh --admin-pass='VotreMotDePasse'
   ```

   Ajouter `--demo` pour générer un troupeau de démonstration sur 3 ans (environ 700 animaux).

4. Serveur web : faire pointer le *DocumentRoot* sur `public/`. Pour un test rapide :

   ```bash
   php -S 127.0.0.1:8080 -t public
   ```

   Si l'application est installée dans un sous-dossier, renseigner `app.base_url` (par exemple `/suivi_troupeau/public`).

5. Production : appliquer `config/php-performance.ini` (OPcache + APCu) et `config/mysql-tuning.cnf`.

Connexion : `admin` avec le mot de passe choisi à l'installation.

## Rôles par défaut

| Rôle | Droits |
|---|---|
| Administrateur | Accès complet |
| Gestionnaire | Animaux et suivis (tous droits), calcul et export des indices, référentiels, consultation des traces |
| Vétérinaire | Consultation, santé (tous droits), saisie des saillies, mises bas et pesées, exports |
| Consultant | Consultation et exports uniquement |

Les permissions se modifient dans *Rôles & permissions*. Le catalogue complet se trouve dans `app/Config/permissions.php`.

## Indices et formules

Les formules utilisent un **moteur d'expressions sécurisé** (`App\Core\FormulaEngine`, sans `eval`) :

- opérateurs `+ - * / % ^`, comparaisons `< <= > >= == !=`, logique `et`, `ou`, `!` ;
- fonctions `min`, `max`, `abs`, `round(x, n)`, `floor`, `ceil`, `sqrt`, `si(condition, a, b)`, `coalesce(a, b, …)`, `nz(x)`, `pct(a, b)` ;
- une division par zéro ou une donnée manquante donne « N/D » (non calculable).

Exemples fournis :

| Indice | Formule |
|---|---|
| Taux de fertilité | `femelles_mises_bas / femelles_reproductrices * 100` |
| Taux de prolificité | `nes_total / mises_bas * 100` |
| Mortalité des jeunes | `morts_jeunes / nes_vivants * 100` |
| Productivité numérique | `sevres / femelles_reproductrices * 100` |
| GMQ (par animal) | `(poids_fin - poids_debut) / jours_pesee * 1000` |
| Intervalle entre mises bas | `intervalle_mises_bas` |

La liste complète des variables (près de 40 pour le troupeau, plus de 30 par animal) s'affiche dans l'éditeur ; un clic sur une variable l'insère dans la formule. Les paramètres zootechniques (âge de sevrage, âge de reproduction, durées de gestation, âge adulte) se règlent dans *Paramètres*.

Chaque résultat enregistré conserve **la formule appliquée et les valeurs des variables**, ce qui garantit la traçabilité même si la formule est modifiée par la suite.

## Architecture

```
public/            point d'entrée unique (index.php), assets (Bootstrap, icônes, Chart.js servis localement)
app/
  Core/            App, Database (PDO), Cache (APCu), Auth (RBAC), Csrf, Validator,
                   KeysetPaginator, Audit, History, Settings, FormulaEngine, Pdf/ (générateur PDF natif)
  Controllers/     Auth, Dashboard, Animal, Tracking (générique), Index, Settings, User, Role, Audit, Reference
  Services/        IndexCalculator, Tracking, Ref, PermissionSync
  Config/          tracking.php (définition déclarative des suivis), permissions.php, labels.php
  Views/           gabarits PHP + Bootstrap 5
database/          schema.sql (InnoDB + index), seed.sql (référentiels, paramètres, indices)
bin/               install.php, demo_data.php
config/            config.php, php-performance.ini, preload.php, mysql-tuning.cnf
```

Pour ajouter un type de suivi, il suffit d'une table et d'une entrée dans `app/Config/tracking.php` : liste, filtres, pagination, formulaire, validation, historique, traces et exports PDF sont générés automatiquement.

## Sécurité

Requêtes préparées natives, protection CSRF sur tous les POST, échappement systématique des sorties, `password_hash` avec rehachage automatique, session régénérée à la connexion (cookies `HttpOnly` et `SameSite`), verrouillage après échecs répétés, en-têtes CSP stricts (aucun script inline, toutes les ressources servies localement), `X-Frame-Options`, et dossiers sensibles protégés par `.htaccess`.
