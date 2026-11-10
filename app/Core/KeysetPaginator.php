<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pagination "keyset" (seek method) : au lieu de LIMIT/OFFSET (qui lit
 * et jette toutes les lignes précédentes), on mémorise la dernière clé
 * (valeur de tri, id) et on demande les lignes situées après elle.
 * Le coût est constant quelle que soit la page, à condition d'avoir un
 * index composite (colonne_de_tri, id) — voir database/schema.sql.
 *
 * Curseur = base64url(json([valeur_tri, id])).
 */
final class KeysetPaginator
{
    private string $dir;

    /**
     * @param string $select  ex. "a.*, c.label AS category_label"
     * @param string $from    ex. "FROM animals a JOIN categories c ON c.id = a.category_id"
     * @param array  $where   conditions SQL (combinées par AND)
     * @param array  $params  paramètres nommés des conditions
     * @param string $sortExpr expression SQL de tri (colonne NOT NULL indexée)
     * @param string $sortKey  clé du tableau résultat contenant la valeur de tri
     */
    public function __construct(
        private string $select,
        private string $from,
        private array $where,
        private array $params,
        private string $sortExpr,
        private string $sortKey,
        string $dir = 'ASC',
        private int $perPage = 25,
        private string $idExpr = 'id',
        private string $idKey = 'id',
    ) {
        $this->dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $max = (int) App::config('pagination.max_per_page', 200);
        $this->perPage = max(1, min($this->perPage, $max));
    }

    public static function encode(mixed $sortValue, int|string $id): string
    {
        return rtrim(strtr(base64_encode(json_encode([$sortValue, $id])), '+/', '-_'), '=');
    }

    public static function decode(?string $cursor): ?array
    {
        if (!$cursor) {
            return null;
        }
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = $json !== false ? json_decode($json, true) : null;
        if (!is_array($data) || count($data) !== 2 || !is_scalar($data[0]) || !is_numeric($data[1])) {
            return null;
        }
        return $data;
    }

    /**
     * @param string|null $cursor
     * @param string $direction 'after' (page suivante) ou 'before' (page précédente)
     */
    public function paginate(?string $cursor, string $direction = 'after'): array
    {
        $key = self::decode($cursor);
        $before = $key !== null && $direction === 'before';
        $where = $this->where;
        $params = $this->params;

        if ($key !== null) {
            // ASC+after ou DESC+before => ">"; sinon "<"
            $op = (($this->dir === 'ASC') xor $before) ? '>' : '<';
            if ($this->sortExpr === $this->idExpr) {
                $where[] = "{$this->idExpr} $op :ks_id";
                $params['ks_id'] = (int) $key[1];
            } else {
                $where[] = "({$this->sortExpr} $op :ks_v1 OR ({$this->sortExpr} = :ks_v2 AND {$this->idExpr} $op :ks_id))";
                $params['ks_v1'] = $key[0];
                $params['ks_v2'] = $key[0];
                $params['ks_id'] = (int) $key[1];
            }
        }

        $order = $before ? ($this->dir === 'ASC' ? 'DESC' : 'ASC') : $this->dir;
        $orderSql = $this->sortExpr === $this->idExpr
            ? "{$this->idExpr} $order"
            : "{$this->sortExpr} $order, {$this->idExpr} $order";

        $sql = sprintf(
            'SELECT %s %s %s ORDER BY %s LIMIT %d',
            $this->select,
            $this->from,
            $where ? 'WHERE ' . implode(' AND ', $where) : '',
            $orderSql,
            $this->perPage + 1
        );
        $rows = Database::all($sql, $params);
        $hasMore = count($rows) > $this->perPage;
        if ($hasMore) {
            array_pop($rows);
        }
        if ($before) {
            $rows = array_reverse($rows);
        }

        $next = $prev = null;
        if ($rows) {
            $first = $rows[0];
            $last = $rows[count($rows) - 1];
            $firstCur = self::encode($first[$this->sortKey], $first[$this->idKey]);
            $lastCur = self::encode($last[$this->sortKey], $last[$this->idKey]);
            if ($key === null) {
                $next = $hasMore ? $lastCur : null;
            } elseif ($before) {
                $next = $lastCur;
                $prev = $hasMore ? $firstCur : null;
            } else {
                $prev = $firstCur;
                $next = $hasMore ? $lastCur : null;
            }
        }

        return [
            'rows'     => $rows,
            'next'     => $next,
            'prev'     => $prev,
            'per_page' => $this->perPage,
            'is_first' => $key === null,
        ];
    }

    /** Nombre total (mis en cache APCu 60 s, dépend du tag fourni). */
    public function count(string $cacheTag): int
    {
        $sql = 'SELECT COUNT(*) ' . $this->from . ($this->where ? ' WHERE ' . implode(' AND ', $this->where) : '');
        $key = Cache::tagged([$cacheTag], 'count:' . md5($sql . serialize($this->params)));
        return (int) Cache::remember($key, 60, fn() => (int) Database::value($sql, $this->params));
    }

    /** Toutes les lignes (pour l'export PDF), bornées par max_export_rows. */
    public function all(): array
    {
        $limit = (int) App::config('pagination.max_export_rows', 5000);
        $orderSql = $this->sortExpr === $this->idExpr
            ? "{$this->idExpr} {$this->dir}"
            : "{$this->sortExpr} {$this->dir}, {$this->idExpr} {$this->dir}";
        $sql = sprintf(
            'SELECT %s %s %s ORDER BY %s LIMIT %d',
            $this->select,
            $this->from,
            $this->where ? 'WHERE ' . implode(' AND ', $this->where) : '',
            $orderSql,
            $limit
        );
        return Database::all($sql, $this->params);
    }
}
