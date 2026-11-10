<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Accès MySQL avec PDO : requêtes préparées natives, InnoDB, transactions.
 */

function bd(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('bd');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['hote'], $c['port'], $c['nom']);
        $pdo = new PDO($dsn, $c['utilisateur'], $c['mot_de_passe'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '" . date('P') . "'",
        ]);
    }
    return $pdo;
}

/**
 * Exécute une requête préparée.
 * Les paramètres nommés utilisés plusieurs fois sont dupliqués automatiquement
 * (les requêtes préparées natives ne l'acceptent pas), et les paramètres
 * inutilisés sont ignorés.
 */
function bd_requete(string $sql, array $params = []): PDOStatement
{
    if ($params && !array_is_list($params)) {
        $vus = [];
        $sql = (string) preg_replace_callback('/(?<![:\w]):([A-Za-z_]\w*)/', static function ($m) use (&$vus, &$params) {
            $nom = $m[1];
            if (!array_key_exists($nom, $params)) {
                return $m[0];
            }
            $vus[$nom] = ($vus[$nom] ?? 0) + 1;
            if ($vus[$nom] === 1) {
                return $m[0];
            }
            $alias = $nom . '__' . $vus[$nom];
            $params[$alias] = $params[$nom];
            return ':' . $alias;
        }, $sql);
        foreach (array_keys($params) as $k) {
            if (!isset($vus[preg_replace('/__\d+$/', '', (string) $k)])) {
                unset($params[$k]);
            }
        }
    }
    $stmt = bd()->prepare($sql);
    foreach ($params as $k => $v) {
        $cle = is_int($k) ? $k + 1 : ':' . $k;
        $type = match (true) {
            is_int($v)  => PDO::PARAM_INT,
            is_bool($v) => PDO::PARAM_BOOL,
            $v === null => PDO::PARAM_NULL,
            default     => PDO::PARAM_STR,
        };
        $stmt->bindValue($cle, $v, $type);
    }
    $stmt->execute();
    return $stmt;
}

function bd_tous(string $sql, array $params = []): array
{
    return bd_requete($sql, $params)->fetchAll();
}

function bd_un(string $sql, array $params = []): ?array
{
    $l = bd_requete($sql, $params)->fetch();
    return $l === false ? null : $l;
}

function bd_valeur(string $sql, array $params = []): mixed
{
    $v = bd_requete($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Tableau clé => valeur (requête à 2 colonnes). */
function bd_paires(string $sql, array $params = []): array
{
    return bd_requete($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
}

function bd_colonne(string $sql, array $params = []): array
{
    return bd_requete($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

function bd_inserer(string $table, array $donnees): int
{
    $cols = array_keys($donnees);
    $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)', $table, implode('`,`', $cols),
        implode(',', array_map(static fn($c) => ':' . $c, $cols)));
    bd_requete($sql, $donnees);
    return (int) bd()->lastInsertId();
}

function bd_modifier(string $table, array $donnees, int $id): int
{
    if (!$donnees) {
        return 0;
    }
    $set = implode(',', array_map(static fn($c) => "`$c` = :$c", array_keys($donnees)));
    $donnees['__id'] = $id;
    return bd_requete("UPDATE `$table` SET $set WHERE id = :__id", $donnees)->rowCount();
}

function bd_supprimer(string $table, int $id): int
{
    return bd_requete("DELETE FROM `$table` WHERE id = :id", ['id' => $id])->rowCount();
}

function bd_trouver(string $table, int $id): ?array
{
    return bd_un("SELECT * FROM `$table` WHERE id = :id", ['id' => $id]);
}

function bd_transaction(callable $fn): mixed
{
    $pdo = bd();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
