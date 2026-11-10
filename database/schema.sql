-- =====================================================================
--  Suivi Troupeau — Schéma MySQL 8 / MariaDB 10.6+ (InnoDB, utf8mb4)
--  Toutes les tables sont en InnoDB avec des index composites conçus
--  pour la pagination keyset (colonne_de_tri, id).
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Sécurité : rôles, permissions, utilisateurs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(60)  NOT NULL,
    description VARCHAR(255) NULL,
    is_super    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Bypass de toutes les permissions',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS permissions;
CREATE TABLE permissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(60)  NOT NULL COMMENT 'module.action',
    module      VARCHAR(40)  NOT NULL,
    label       VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS role_permissions;
CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id         INT UNSIGNED NOT NULL,
    username        VARCHAR(60)  NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(160) NULL,
    password_hash   VARCHAR(255) NOT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   DATETIME     NULL,
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id, id),
    KEY idx_users_active (is_active, id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Traces (journal d'audit)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    username    VARCHAR(60)  NULL COMMENT 'Dénormalisé : conservé même si l''utilisateur est supprimé',
    action      VARCHAR(30)  NOT NULL COMMENT 'create, update, delete, login, logout, export, compute...',
    entity      VARCHAR(40)  NOT NULL,
    entity_id   BIGINT UNSIGNED NULL,
    summary     VARCHAR(255) NULL,
    old_values  JSON NULL,
    new_values  JSON NULL,
    ip_address  VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at, id),
    KEY idx_audit_user (user_id, id),
    KEY idx_audit_entity (entity, entity_id, id),
    KEY idx_audit_action (action, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Paramètres
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
    skey        VARCHAR(60)  NOT NULL,
    svalue      TEXT         NULL,
    stype       VARCHAR(20)  NOT NULL DEFAULT 'text' COMMENT 'text, int, number, bool',
    label       VARCHAR(160) NOT NULL,
    sgroup      VARCHAR(40)  NOT NULL DEFAULT 'general',
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY (skey),
    KEY idx_settings_group (sgroup, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Référentiels
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(30)  NOT NULL,
    label       VARCHAR(60)  NOT NULL,
    species     ENUM('caprin','ovin') NOT NULL,
    sex         ENUM('M','F') NOT NULL,
    is_young    TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_code (code),
    KEY idx_categories_species (species, sex)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS breeds;
CREATE TABLE breeds (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80)  NOT NULL,
    species     ENUM('caprin','ovin') NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_breeds (species, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS lots;
CREATE TABLE lots (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80)  NOT NULL,
    location    VARCHAR(120) NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lots_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Animaux (fiche animal)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS animals;
CREATE TABLE animals (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tag             VARCHAR(30)  NOT NULL COMMENT 'N° boucle / identifiant officiel',
    name            VARCHAR(80)  NULL,
    species         ENUM('caprin','ovin') NOT NULL,
    category_id     SMALLINT UNSIGNED NOT NULL,
    sex             ENUM('M','F') NOT NULL,
    breed_id        SMALLINT UNSIGNED NULL,
    lot_id          SMALLINT UNSIGNED NULL,
    birth_date      DATE         NOT NULL,
    birth_date_estimated TINYINT(1) NOT NULL DEFAULT 0,
    birth_weight    DECIMAL(6,2) NULL COMMENT 'kg',
    birth_type      ENUM('simple','double','triple','quadruple+') NULL,
    mother_id       INT UNSIGNED NULL,
    father_id       INT UNSIGNED NULL,
    origin          ENUM('naissance','achat','don','autre') NOT NULL DEFAULT 'naissance',
    entry_date      DATE         NOT NULL,
    purchase_price  DECIMAL(10,2) NULL,
    color           VARCHAR(60)  NULL,
    status          ENUM('actif','vendu','mort','reforme','perdu') NOT NULL DEFAULT 'actif',
    exit_date       DATE         NULL,
    exit_reason     VARCHAR(160) NULL,
    sale_price      DECIMAL(10,2) NULL,
    notes           TEXT         NULL,
    created_by      INT UNSIGNED NULL,
    updated_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_animals_tag (tag),
    -- index pensés pour la recherche avancée + keyset (filtre, tri, id)
    KEY idx_animals_status (status, id),
    KEY idx_animals_species_status (species, status, id),
    KEY idx_animals_category (category_id, status, id),
    KEY idx_animals_breed (breed_id, id),
    KEY idx_animals_lot (lot_id, id),
    KEY idx_animals_birth (birth_date, id),
    KEY idx_animals_entry (entry_date, id),
    KEY idx_animals_exit (exit_date, status),
    KEY idx_animals_mother (mother_id),
    KEY idx_animals_father (father_id),
    KEY idx_animals_name (name),
    CONSTRAINT fk_animals_category FOREIGN KEY (category_id) REFERENCES categories (id),
    CONSTRAINT fk_animals_breed    FOREIGN KEY (breed_id)    REFERENCES breeds (id) ON DELETE SET NULL,
    CONSTRAINT fk_animals_lot      FOREIGN KEY (lot_id)      REFERENCES lots (id)   ON DELETE SET NULL,
    CONSTRAINT fk_animals_mother   FOREIGN KEY (mother_id)   REFERENCES animals (id) ON DELETE SET NULL,
    CONSTRAINT fk_animals_father   FOREIGN KEY (father_id)   REFERENCES animals (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique fonctionnel de l'animal (chronologie)
DROP TABLE IF EXISTS animal_history;
CREATE TABLE animal_history (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id   INT UNSIGNED NOT NULL,
    event_date  DATE         NOT NULL,
    event_type  VARCHAR(30)  NOT NULL COMMENT 'creation, modification, pesee, sante, saillie, mise_bas, lait, mouvement, sortie...',
    ref_table   VARCHAR(40)  NULL,
    ref_id      BIGINT UNSIGNED NULL,
    summary     VARCHAR(500) NOT NULL,
    changes     JSON NULL,
    user_id     INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ah_animal (animal_id, event_date, id),
    KEY idx_ah_ref (ref_table, ref_id),
    KEY idx_ah_type (event_type, event_date),
    CONSTRAINT fk_ah_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Suivis
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS weighings;
CREATE TABLE weighings (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id   INT UNSIGNED NOT NULL,
    event_date  DATE         NOT NULL,
    weight      DECIMAL(6,2) NOT NULL COMMENT 'kg',
    body_score  DECIMAL(2,1) NULL COMMENT 'Note d''état corporel 1-5',
    context     ENUM('routine','naissance','sevrage','vente','autre') NOT NULL DEFAULT 'routine',
    notes       VARCHAR(255) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_w_animal (animal_id, event_date, id),
    KEY idx_w_date (event_date, id),
    CONSTRAINT fk_w_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS health_events;
CREATE TABLE health_events (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL,
    event_date      DATE         NOT NULL,
    event_type      ENUM('vaccination','vermifuge','traitement','maladie','blessure','visite','autre') NOT NULL,
    diagnosis       VARCHAR(160) NULL,
    product         VARCHAR(120) NULL,
    dose            VARCHAR(60)  NULL,
    veterinarian    VARCHAR(120) NULL,
    cost            DECIMAL(10,2) NULL,
    withdrawal_days SMALLINT UNSIGNED NULL COMMENT 'Délai d''attente (jours)',
    next_date       DATE         NULL COMMENT 'Rappel',
    outcome         ENUM('en_cours','gueri','chronique','mort','na') NOT NULL DEFAULT 'na',
    notes           VARCHAR(500) NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_h_animal (animal_id, event_date, id),
    KEY idx_h_date (event_date, id),
    KEY idx_h_type (event_type, event_date, id),
    KEY idx_h_next (next_date),
    CONSTRAINT fk_h_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS matings;
CREATE TABLE matings (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL COMMENT 'Femelle',
    male_id         INT UNSIGNED NULL,
    event_date      DATE         NOT NULL,
    method          ENUM('monte_naturelle','insemination','lutte_libre') NOT NULL DEFAULT 'monte_naturelle',
    diagnosis       ENUM('non_fait','gestante','vide','douteux') NOT NULL DEFAULT 'non_fait',
    diagnosis_date  DATE         NULL,
    expected_birth  DATE         NULL,
    notes           VARCHAR(255) NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_m_animal (animal_id, event_date, id),
    KEY idx_m_male (male_id, event_date),
    KEY idx_m_date (event_date, id),
    KEY idx_m_expected (expected_birth),
    CONSTRAINT fk_m_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE,
    CONSTRAINT fk_m_male   FOREIGN KEY (male_id)   REFERENCES animals (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS births;
CREATE TABLE births (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id       INT UNSIGNED NOT NULL COMMENT 'Mère',
    father_id       INT UNSIGNED NULL,
    event_date      DATE         NOT NULL,
    born_total      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    born_alive      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    born_males      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    born_females    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ease            ENUM('facile','aidee','difficile','cesarienne') NOT NULL DEFAULT 'facile',
    abortion        TINYINT(1)   NOT NULL DEFAULT 0,
    notes           VARCHAR(255) NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_b_animal (animal_id, event_date, id),
    KEY idx_b_date (event_date, id),
    KEY idx_b_father (father_id),
    CONSTRAINT fk_b_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE,
    CONSTRAINT fk_b_father FOREIGN KEY (father_id) REFERENCES animals (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS milk_records;
CREATE TABLE milk_records (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id   INT UNSIGNED NOT NULL,
    event_date  DATE         NOT NULL,
    quantity    DECIMAL(6,2) NOT NULL COMMENT 'Litres / jour',
    fat_rate    DECIMAL(4,1) NULL COMMENT 'TB g/L',
    protein_rate DECIMAL(4,1) NULL COMMENT 'TP g/L',
    notes       VARCHAR(255) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_milk_animal (animal_id, event_date, id),
    KEY idx_milk_date (event_date, id),
    CONSTRAINT fk_milk_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS movements;
CREATE TABLE movements (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id   INT UNSIGNED NOT NULL,
    event_date  DATE         NOT NULL,
    movement_type ENUM('changement_lot','transhumance','pret','retour','autre') NOT NULL DEFAULT 'changement_lot',
    from_lot_id SMALLINT UNSIGNED NULL,
    to_lot_id   SMALLINT UNSIGNED NULL,
    notes       VARCHAR(255) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mv_animal (animal_id, event_date, id),
    KEY idx_mv_date (event_date, id),
    CONSTRAINT fk_mv_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE,
    CONSTRAINT fk_mv_from FOREIGN KEY (from_lot_id) REFERENCES lots (id) ON DELETE SET NULL,
    CONSTRAINT fk_mv_to   FOREIGN KEY (to_lot_id)   REFERENCES lots (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Indices zootechniques : définitions (formules paramétrables) et résultats
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS index_definitions;
CREATE TABLE index_definitions (
    id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(40)  NOT NULL,
    name            VARCHAR(120) NOT NULL,
    description     VARCHAR(500) NULL,
    scope           ENUM('animal','troupeau') NOT NULL COMMENT 'animal = par animal ; troupeau = par année / espèce / catégorie / race / lot',
    formula         VARCHAR(1000) NOT NULL,
    unit            VARCHAR(20)  NULL,
    decimals        TINYINT UNSIGNED NOT NULL DEFAULT 2,
    better          ENUM('haut','bas') NOT NULL DEFAULT 'haut' COMMENT 'Sens favorable',
    threshold_low   DECIMAL(14,4) NULL,
    threshold_high  DECIMAL(14,4) NULL,
    text_good       VARCHAR(255) NULL,
    text_medium     VARCHAR(255) NULL,
    text_bad        VARCHAR(255) NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    updated_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_index_code (code),
    KEY idx_index_scope (scope, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des formules (chaque modification est versionnée)
DROP TABLE IF EXISTS index_formula_history;
CREATE TABLE index_formula_history (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    index_id    SMALLINT UNSIGNED NOT NULL,
    old_formula VARCHAR(1000) NULL,
    new_formula VARCHAR(1000) NOT NULL,
    user_id     INT UNSIGNED NULL,
    changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ifh_index (index_id, changed_at),
    CONSTRAINT fk_ifh_index FOREIGN KEY (index_id) REFERENCES index_definitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS index_results;
CREATE TABLE index_results (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    index_id        SMALLINT UNSIGNED NOT NULL,
    group_type      ENUM('animal','global','espece','categorie','race','lot') NOT NULL,
    group_value     VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'id animal, espèce, id catégorie...',
    group_label     VARCHAR(120) NULL,
    animal_id       INT UNSIGNED NULL,
    year            SMALLINT UNSIGNED NOT NULL COMMENT '0 = vie entière',
    value           DECIMAL(16,4) NULL,
    level           ENUM('bon','moyen','faible','na') NOT NULL DEFAULT 'na',
    interpretation  VARCHAR(500) NULL,
    variables       JSON NULL COMMENT 'Valeurs des variables utilisées',
    formula_used    VARCHAR(1000) NOT NULL,
    computed_by     INT UNSIGNED NULL,
    computed_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_index_result (index_id, group_type, group_value, year),
    KEY idx_ir_year (year, index_id, id),
    KEY idx_ir_animal (animal_id, year),
    KEY idx_ir_group (group_type, year, id),
    KEY idx_ir_level (level, id),
    CONSTRAINT fk_ir_index  FOREIGN KEY (index_id)  REFERENCES index_definitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_ir_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
