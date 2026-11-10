-- =====================================================================
--  Suivi Troupeau — schéma de la base (MySQL 8 / MariaDB 10.6+)
--  Moteur InnoDB, jeu de caractères utf8mb4, noms de tables et de colonnes en français.
--  Index composites (filtre, colonne_de_tri, id) pour la pagination keyset.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Sécurité : rôles, permissions, utilisateurs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom            VARCHAR(60)  NOT NULL,
    description    VARCHAR(255) NULL,
    acces_complet  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Toutes les permissions',
    cree_le        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS permissions;
CREATE TABLE permissions (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code     VARCHAR(60)  NOT NULL COMMENT 'module.action',
    module   VARCHAR(40)  NOT NULL,
    libelle  VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS roles_permissions;
CREATE TABLE roles_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS utilisateurs;
CREATE TABLE utilisateurs (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id             INT UNSIGNED NOT NULL,
    identifiant         VARCHAR(60)  NOT NULL,
    nom_complet         VARCHAR(120) NOT NULL,
    email               VARCHAR(160) NULL,
    mot_de_passe        VARCHAR(255) NOT NULL COMMENT 'Hachage password_hash()',
    actif               TINYINT(1)   NOT NULL DEFAULT 1,
    derniere_connexion  DATETIME     NULL,
    tentatives_echouees SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloque_jusqua       DATETIME     NULL,
    cree_le             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_utilisateurs_identifiant (identifiant),
    UNIQUE KEY uq_utilisateurs_email (email),
    KEY idx_utilisateurs_role (role_id, id),
    KEY idx_utilisateurs_actif (actif, id),
    CONSTRAINT fk_utilisateurs_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Traces (journal d'audit)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS traces;
CREATE TABLE traces (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id     INT UNSIGNED NULL,
    identifiant        VARCHAR(60)  NULL COMMENT 'Conservé même si l''utilisateur est supprimé',
    action             VARCHAR(30)  NOT NULL COMMENT 'ajout, modification, suppression, connexion, export, calcul...',
    objet              VARCHAR(40)  NOT NULL,
    objet_id           BIGINT UNSIGNED NULL,
    resume             VARCHAR(255) NULL,
    anciennes_valeurs  JSON NULL,
    nouvelles_valeurs  JSON NULL,
    adresse_ip         VARCHAR(45)  NULL,
    navigateur         VARCHAR(255) NULL,
    cree_le            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_traces_date (cree_le, id),
    KEY idx_traces_utilisateur (utilisateur_id, id),
    KEY idx_traces_objet (objet, objet_id, id),
    KEY idx_traces_action (action, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Paramètres
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS parametres;
CREATE TABLE parametres (
    cle      VARCHAR(60)  NOT NULL,
    valeur   TEXT         NULL,
    type     VARCHAR(20)  NOT NULL DEFAULT 'texte' COMMENT 'texte, entier, nombre',
    libelle  VARCHAR(160) NOT NULL,
    groupe   VARCHAR(40)  NOT NULL DEFAULT 'general',
    ordre    SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY (cle),
    KEY idx_parametres_groupe (groupe, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Référentiels
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code      VARCHAR(30) NOT NULL,
    libelle   VARCHAR(60) NOT NULL,
    espece    ENUM('caprin','ovin') NOT NULL,
    sexe      ENUM('M','F') NOT NULL,
    est_jeune TINYINT(1)  NOT NULL DEFAULT 0,
    ordre     SMALLINT    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_code (code),
    KEY idx_categories_espece (espece, sexe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS races;
CREATE TABLE races (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(80)  NOT NULL,
    espece      ENUM('caprin','ovin') NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_races (espece, nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS lots;
CREATE TABLE lots (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(80)  NOT NULL,
    emplacement VARCHAR(120) NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lots_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Animaux (fiche animal)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS animaux;
CREATE TABLE animaux (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    boucle             VARCHAR(30)  NOT NULL COMMENT 'N° de boucle / identifiant officiel',
    nom                VARCHAR(80)  NULL,
    espece             ENUM('caprin','ovin') NOT NULL,
    categorie_id       SMALLINT UNSIGNED NOT NULL,
    sexe               ENUM('M','F') NOT NULL,
    race_id            SMALLINT UNSIGNED NULL,
    lot_id             SMALLINT UNSIGNED NULL,
    date_naissance     DATE         NOT NULL,
    naissance_estimee  TINYINT(1)   NOT NULL DEFAULT 0,
    poids_naissance    DECIMAL(6,2) NULL COMMENT 'kg',
    type_naissance     ENUM('simple','double','triple','quadruple+') NULL,
    mere_id            INT UNSIGNED NULL,
    pere_id            INT UNSIGNED NULL,
    origine            ENUM('naissance','achat','don','autre') NOT NULL DEFAULT 'naissance',
    date_entree        DATE         NOT NULL,
    prix_achat         DECIMAL(10,2) NULL,
    robe               VARCHAR(60)  NULL,
    statut             ENUM('actif','vendu','mort','reforme','perdu') NOT NULL DEFAULT 'actif',
    date_sortie        DATE         NULL,
    motif_sortie       VARCHAR(160) NULL,
    prix_vente         DECIMAL(10,2) NULL,
    remarques          TEXT         NULL,
    cree_par           INT UNSIGNED NULL,
    modifie_par        INT UNSIGNED NULL,
    cree_le            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le         DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_animaux_boucle (boucle),
    KEY idx_animaux_statut (statut, id),
    KEY idx_animaux_espece_statut (espece, statut, id),
    KEY idx_animaux_categorie (categorie_id, statut, id),
    KEY idx_animaux_race (race_id, id),
    KEY idx_animaux_lot (lot_id, id),
    KEY idx_animaux_naissance (date_naissance, id),
    KEY idx_animaux_entree (date_entree, id),
    KEY idx_animaux_sortie (date_sortie, statut),
    KEY idx_animaux_mere (mere_id),
    KEY idx_animaux_pere (pere_id),
    KEY idx_animaux_nom (nom),
    CONSTRAINT fk_animaux_categorie FOREIGN KEY (categorie_id) REFERENCES categories (id),
    CONSTRAINT fk_animaux_race FOREIGN KEY (race_id) REFERENCES races (id) ON DELETE SET NULL,
    CONSTRAINT fk_animaux_lot  FOREIGN KEY (lot_id)  REFERENCES lots (id)  ON DELETE SET NULL,
    CONSTRAINT fk_animaux_mere FOREIGN KEY (mere_id) REFERENCES animaux (id) ON DELETE SET NULL,
    CONSTRAINT fk_animaux_pere FOREIGN KEY (pere_id) REFERENCES animaux (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique (chronologie) de chaque animal
DROP TABLE IF EXISTS historique_animaux;
CREATE TABLE historique_animaux (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL,
    date_evenement  DATE         NOT NULL,
    type_evenement  VARCHAR(30)  NOT NULL COMMENT 'creation, modification, pesee, soin, saillie, mise_bas, lait, mouvement, sortie...',
    table_ref       VARCHAR(40)  NULL,
    ref_id          BIGINT UNSIGNED NULL,
    resume          VARCHAR(500) NOT NULL,
    changements     JSON NULL,
    utilisateur_id  INT UNSIGNED NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ha_animal (animal_id, date_evenement, id),
    KEY idx_ha_ref (table_ref, ref_id),
    KEY idx_ha_type (type_evenement, date_evenement),
    CONSTRAINT fk_ha_animal FOREIGN KEY (animal_id) REFERENCES animaux (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Suivis
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS pesees;
CREATE TABLE pesees (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL,
    date_evenement  DATE         NOT NULL,
    poids           DECIMAL(6,2) NOT NULL COMMENT 'kg',
    note_etat       DECIMAL(2,1) NULL COMMENT 'Note d''état corporel 1 à 5',
    contexte        ENUM('routine','naissance','sevrage','vente','autre') NOT NULL DEFAULT 'routine',
    remarques       VARCHAR(255) NULL,
    cree_par        INT UNSIGNED NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pesees_animal (animal_id, date_evenement, id),
    KEY idx_pesees_date (date_evenement, id),
    CONSTRAINT fk_pesees_animal FOREIGN KEY (animal_id) REFERENCES animaux (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS soins;
CREATE TABLE soins (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL,
    date_evenement  DATE         NOT NULL,
    type_soin       ENUM('vaccination','vermifuge','traitement','maladie','blessure','visite','autre') NOT NULL,
    diagnostic      VARCHAR(160) NULL,
    produit         VARCHAR(120) NULL,
    dose            VARCHAR(60)  NULL,
    veterinaire     VARCHAR(120) NULL,
    cout            DECIMAL(10,2) NULL,
    delai_attente   SMALLINT UNSIGNED NULL COMMENT 'jours',
    date_rappel     DATE         NULL,
    evolution       ENUM('na','en_cours','gueri','chronique','mort') NOT NULL DEFAULT 'na',
    remarques       VARCHAR(500) NULL,
    cree_par        INT UNSIGNED NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_soins_animal (animal_id, date_evenement, id),
    KEY idx_soins_date (date_evenement, id),
    KEY idx_soins_type (type_soin, date_evenement, id),
    KEY idx_soins_rappel (date_rappel),
    CONSTRAINT fk_soins_animal FOREIGN KEY (animal_id) REFERENCES animaux (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS saillies;
CREATE TABLE saillies (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id        INT UNSIGNED NOT NULL COMMENT 'Femelle',
    male_id          INT UNSIGNED NULL,
    date_evenement   DATE         NOT NULL,
    methode          ENUM('monte_naturelle','insemination','lutte_libre') NOT NULL DEFAULT 'monte_naturelle',
    diagnostic       ENUM('non_fait','gestante','vide','douteux') NOT NULL DEFAULT 'non_fait',
    date_diagnostic  DATE         NULL,
    mise_bas_prevue  DATE         NULL,
    remarques        VARCHAR(255) NULL,
    cree_par         INT UNSIGNED NULL,
    cree_le          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_saillies_animal (animal_id, date_evenement, id),
    KEY idx_saillies_male (male_id, date_evenement),
    KEY idx_saillies_date (date_evenement, id),
    KEY idx_saillies_prevue (mise_bas_prevue),
    CONSTRAINT fk_saillies_animal FOREIGN KEY (animal_id) REFERENCES animaux (id) ON DELETE CASCADE,
    CONSTRAINT fk_saillies_male   FOREIGN KEY (male_id)   REFERENCES animaux (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS mises_bas;
CREATE TABLE mises_bas (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL COMMENT 'Mère',
    pere_id         INT UNSIGNED NULL,
    date_evenement  DATE         NOT NULL,
    nes_total       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    nes_vivants     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    nes_males       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    nes_femelles    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    deroulement     ENUM('facile','aidee','difficile','cesarienne') NOT NULL DEFAULT 'facile',
    avortement      TINYINT(1)   NOT NULL DEFAULT 0,
    remarques       VARCHAR(255) NULL,
    cree_par        INT UNSIGNED NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mb_animal (animal_id, date_evenement, id),
    KEY idx_mb_date (date_evenement, id),
    KEY idx_mb_pere (pere_id),
    CONSTRAINT fk_mb_animal FOREIGN KEY (animal_id) REFERENCES animaux (id) ON DELETE CASCADE,
    CONSTRAINT fk_mb_pere   FOREIGN KEY (pere_id)   REFERENCES animaux (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS controles_laitiers;
CREATE TABLE controles_laitiers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL,
    date_evenement  DATE         NOT NULL,
    quantite        DECIMAL(6,2) NOT NULL COMMENT 'Litres / jour',
    taux_butyreux   DECIMAL(4,1) NULL COMMENT 'g/L',
    taux_proteique  DECIMAL(4,1) NULL COMMENT 'g/L',
    remarques       VARCHAR(255) NULL,
    cree_par        INT UNSIGNED NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lait_animal (animal_id, date_evenement, id),
    KEY idx_lait_date (date_evenement, id),
    CONSTRAINT fk_lait_animal FOREIGN KEY (animal_id) REFERENCES animaux (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS mouvements;
CREATE TABLE mouvements (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL,
    date_evenement  DATE         NOT NULL,
    type_mouvement  ENUM('changement_lot','transhumance','pret','retour','autre') NOT NULL DEFAULT 'changement_lot',
    lot_depart_id   SMALLINT UNSIGNED NULL,
    lot_arrivee_id  SMALLINT UNSIGNED NULL,
    remarques       VARCHAR(255) NULL,
    cree_par        INT UNSIGNED NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mvt_animal (animal_id, date_evenement, id),
    KEY idx_mvt_date (date_evenement, id),
    CONSTRAINT fk_mvt_animal  FOREIGN KEY (animal_id)      REFERENCES animaux (id) ON DELETE CASCADE,
    CONSTRAINT fk_mvt_depart  FOREIGN KEY (lot_depart_id)  REFERENCES lots (id) ON DELETE SET NULL,
    CONSTRAINT fk_mvt_arrivee FOREIGN KEY (lot_arrivee_id) REFERENCES lots (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Indices zootechniques : définitions (formules modifiables) et résultats
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS indices;
CREATE TABLE indices (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(40)   NOT NULL,
    nom          VARCHAR(120)  NOT NULL,
    description  VARCHAR(500)  NULL,
    portee       ENUM('animal','troupeau') NOT NULL COMMENT 'animal = par animal ; troupeau = par année et par groupe',
    formule      VARCHAR(1000) NOT NULL,
    unite        VARCHAR(20)   NULL,
    decimales    TINYINT UNSIGNED NOT NULL DEFAULT 2,
    sens         ENUM('haut','bas') NOT NULL DEFAULT 'haut' COMMENT 'Sens favorable',
    seuil_bas    DECIMAL(14,4) NULL,
    seuil_haut   DECIMAL(14,4) NULL,
    texte_bon    VARCHAR(255)  NULL,
    texte_moyen  VARCHAR(255)  NULL,
    texte_faible VARCHAR(255)  NULL,
    actif        TINYINT(1)    NOT NULL DEFAULT 1,
    ordre        SMALLINT      NOT NULL DEFAULT 0,
    modifie_par  INT UNSIGNED  NULL,
    cree_le      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_indices_code (code),
    KEY idx_indices_portee (portee, actif, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS historique_formules;
CREATE TABLE historique_formules (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    indice_id         SMALLINT UNSIGNED NOT NULL,
    ancienne_formule  VARCHAR(1000) NULL,
    nouvelle_formule  VARCHAR(1000) NOT NULL,
    utilisateur_id    INT UNSIGNED NULL,
    modifie_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hf_indice (indice_id, modifie_le),
    CONSTRAINT fk_hf_indice FOREIGN KEY (indice_id) REFERENCES indices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS resultats_indices;
CREATE TABLE resultats_indices (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    indice_id        SMALLINT UNSIGNED NOT NULL,
    type_groupe      ENUM('animal','troupeau','espece','categorie','race','lot') NOT NULL,
    valeur_groupe    VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'id animal, espèce, id catégorie...',
    libelle_groupe   VARCHAR(120) NULL,
    animal_id        INT UNSIGNED NULL,
    annee            SMALLINT UNSIGNED NOT NULL COMMENT '0 = vie entière',
    valeur           DECIMAL(16,4) NULL,
    niveau           ENUM('bon','moyen','faible','nd') NOT NULL DEFAULT 'nd',
    interpretation   VARCHAR(500) NULL,
    variables        JSON NULL COMMENT 'Valeurs des variables utilisées',
    formule_utilisee VARCHAR(1000) NOT NULL,
    calcule_par      INT UNSIGNED NULL,
    calcule_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resultat (indice_id, type_groupe, valeur_groupe, annee),
    KEY idx_ri_annee (annee, indice_id, id),
    KEY idx_ri_animal (animal_id, annee),
    KEY idx_ri_groupe (type_groupe, annee, id),
    KEY idx_ri_niveau (niveau, id),
    CONSTRAINT fk_ri_indice FOREIGN KEY (indice_id) REFERENCES indices (id) ON DELETE CASCADE,
    CONSTRAINT fk_ri_animal FOREIGN KEY (animal_id) REFERENCES animaux (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
