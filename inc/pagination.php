<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Pagination « keyset » (méthode du curseur).
 *
 * Au lieu de LIMIT … OFFSET (qui lit puis jette toutes les lignes des pages
 * précédentes), on retient la dernière clé affichée (valeur de tri, id) et on
 * demande les lignes situées après elle :
 *     WHERE (tri > :v OR (tri = :v AND id > :id)) ORDER BY tri, id LIMIT n+1
 * Le coût reste constant quelle que soit la page, grâce aux index
 * composites (colonne_de_tri, id) définis dans sql/schema.sql.
 *
 * Description d'une requête ($req) :
 *   select   colonnes                 ex. 'a.*, c.libelle AS categorie'
 *   from     FROM + jointures
 *   where    liste de conditions      (combinées par AND)
 *   params   paramètres nommés
 *   tri      expression SQL de tri    (colonne NOT NULL et indexée)
 *   cle_tri  clé de la ligne contenant la valeur de tri
 *   id       expression SQL de l'identifiant (défaut 'id')
 *   sens     'ASC' ou 'DESC'
 *   par_page nombre de lignes
 */

function curseur_encoder(mixed $valeur, int|string $id): string
{
    return rtrim(strtr(base64_encode(json_encode([$valeur, $id])), '+/', '-_'), '=');
}

function curseur_decoder(?string $c): ?array
{
    if (!$c) {
        return null;
    }
    $json = base64_decode(strtr($c, '-_', '+/'), true);
    $d = $json !== false ? json_decode($json, true) : null;
    return (is_array($d) && count($d) === 2 && is_scalar($d[0]) && is_numeric($d[1])) ? $d : null;
}

function requete_normaliser(array $req): array
{
    $req += ['where' => [], 'params' => [], 'id' => 'id', 'cle_id' => 'id', 'sens' => 'ASC', 'par_page' => 25];
    $req['sens'] = strtoupper($req['sens']) === 'DESC' ? 'DESC' : 'ASC';
    $req['par_page'] = max(1, min((int) $req['par_page'], (int) config('pagination.max_par_page', 200)));
    return $req;
}

/**
 * Exécute la page demandée (curseur et direction lus dans $_GET).
 * @return array ['lignes', 'suivant', 'precedent', 'par_page', 'premiere']
 */
function paginer(array $req): array
{
    $req = requete_normaliser($req);
    $cle = curseur_decoder($_GET['curseur'] ?? null);
    $avant = $cle !== null && ($_GET['sens_page'] ?? '') === 'avant';
    $where = $req['where'];
    $params = $req['params'];

    if ($cle !== null) {
        $op = (($req['sens'] === 'ASC') xor $avant) ? '>' : '<';
        if ($req['tri'] === $req['id']) {
            $where[] = "{$req['id']} $op :ks_id";
        } else {
            $where[] = "({$req['tri']} $op :ks_v OR ({$req['tri']} = :ks_v AND {$req['id']} $op :ks_id))";
            $params['ks_v'] = $cle[0];
        }
        $params['ks_id'] = (int) $cle[1];
    }
    $sens = $avant ? ($req['sens'] === 'ASC' ? 'DESC' : 'ASC') : $req['sens'];
    $ordre = $req['tri'] === $req['id'] ? "{$req['id']} $sens" : "{$req['tri']} $sens, {$req['id']} $sens";
    $sql = "SELECT {$req['select']} {$req['from']}" . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY $ordre LIMIT " . ($req['par_page'] + 1);

    $lignes = bd_tous($sql, $params);
    $encore = count($lignes) > $req['par_page'];
    if ($encore) {
        array_pop($lignes);
    }
    if ($avant) {
        $lignes = array_reverse($lignes);
    }
    $suivant = $precedent = null;
    if ($lignes) {
        $p = $lignes[0];
        $d = $lignes[count($lignes) - 1];
        $cp = curseur_encoder($p[$req['cle_tri']], $p[$req['cle_id']]);
        $cd = curseur_encoder($d[$req['cle_tri']], $d[$req['cle_id']]);
        if ($cle === null) {
            $suivant = $encore ? $cd : null;
        } elseif ($avant) {
            $suivant = $cd;
            $precedent = $encore ? $cp : null;
        } else {
            $precedent = $cp;
            $suivant = $encore ? $cd : null;
        }
    }
    return ['lignes' => $lignes, 'suivant' => $suivant, 'precedent' => $precedent,
            'par_page' => $req['par_page'], 'premiere' => $cle === null];
}

/** Nombre total de lignes (mis en cache APCu 60 s, invalidé avec l'étiquette). */
function compter(array $req, string $etiquette): int
{
    $req = requete_normaliser($req);
    $sql = 'SELECT COUNT(*) ' . $req['from'] . ($req['where'] ? ' WHERE ' . implode(' AND ', $req['where']) : '');
    $cle = cache_cle([$etiquette], 'total:' . md5($sql . serialize($req['params'])));
    return (int) cache_memoriser($cle, 60, static fn() => (int) bd_valeur($sql, $req['params']));
}

/** Toutes les lignes (exports PDF), limitées à pagination.max_lignes_pdf. */
function tout_lire(array $req): array
{
    $req = requete_normaliser($req);
    $ordre = $req['tri'] === $req['id'] ? "{$req['id']} {$req['sens']}" : "{$req['tri']} {$req['sens']}, {$req['id']} {$req['sens']}";
    $sql = "SELECT {$req['select']} {$req['from']}" . ($req['where'] ? ' WHERE ' . implode(' AND ', $req['where']) : '')
        . " ORDER BY $ordre LIMIT " . (int) config('pagination.max_lignes_pdf', 5000);
    return bd_tous($sql, $req['params']);
}
