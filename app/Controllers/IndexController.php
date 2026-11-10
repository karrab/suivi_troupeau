<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Database;
use App\Core\KeysetPaginator;
use App\Core\Pdf\PdfReport;
use App\Services\IndexCalculator;

final class IndexController extends Controller
{
    private const GROUP_TYPES = ['global', 'espece', 'categorie', 'race', 'lot'];

    private function years(): array
    {
        $min = (int) (Database::value('SELECT YEAR(MIN(entry_date)) FROM animals') ?: date('Y'));
        return range((int) date('Y'), min($min, (int) date('Y')));
    }

    /** Tableau de bord des indices du troupeau pour une année. */
    public function index(): never
    {
        $this->authorize('indices.view');
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) (Database::value("SELECT MAX(year) FROM index_results WHERE group_type = 'global'") ?? date('Y'));
        $groupType = in_array($_GET['group_type'] ?? '', self::GROUP_TYPES, true) ? $_GET['group_type'] : 'global';
        $results = Cache::remember(Cache::tagged(['indices'], "overview:$year:$groupType"), 300, static fn() => Database::all(
            'SELECT r.*, d.name, d.code, d.unit, d.decimals, d.description FROM index_results r
             JOIN index_definitions d ON d.id = r.index_id
             WHERE r.year = :y AND r.group_type = :g ORDER BY r.group_label, d.sort_order',
            ['y' => $year, 'g' => $groupType]
        ));
        $grouped = [];
        foreach ($results as $r) {
            $grouped[$r['group_label'] ?: 'Troupeau entier'][] = $r;
        }
        $animalSummary = Database::all(
            "SELECT d.name, d.unit, d.decimals, COUNT(r.value) AS n, AVG(r.value) AS avg_v, MIN(r.value) AS min_v, MAX(r.value) AS max_v,
                    SUM(r.level = 'bon') AS bon, SUM(r.level = 'moyen') AS moyen, SUM(r.level = 'faible') AS faible, d.id
             FROM index_definitions d LEFT JOIN index_results r ON r.index_id = d.id AND r.group_type = 'animal' AND r.year = :y
             WHERE d.scope = 'animal' AND d.is_active = 1 GROUP BY d.id, d.name, d.unit, d.decimals, d.sort_order ORDER BY d.sort_order",
            ['y' => $year]
        );
        $this->render('indices/index', [
            'year' => $year, 'years' => $this->years(), 'groupType' => $groupType, 'grouped' => $grouped,
            'animalSummary' => $animalSummary,
            'herdDefs' => IndexCalculator::definitions('troupeau'), 'animalDefs' => IndexCalculator::definitions('animal'),
        ]);
    }

    public function compute(): never
    {
        $this->authorize('indices.compute');
        $year = (int) ($_POST['year'] ?? date('Y'));
        $calc = new IndexCalculator();
        $ids = array_map('intval', (array) ($_POST['index_ids'] ?? []));
        $msg = [];
        $t = microtime(true);
        if (($_POST['target'] ?? 'troupeau') === 'animal') {
            $r = $calc->computeAnimals($year, null, $ids);
            $msg[] = "{$r['saved']} résultat(s) pour {$r['animals']} animal(aux)";
            $redirect = url('indices/results', ['year' => $year, 'group_type' => 'animal']);
        } else {
            $types = array_intersect((array) ($_POST['group_types'] ?? ['global']), self::GROUP_TYPES) ?: ['global'];
            foreach ($types as $gt) {
                $r = $calc->computeHerd($year, $gt, $ids);
                $msg[] = label('group_type', $gt) . ' : ' . $r['saved'];
            }
            $redirect = url('indices', ['year' => $year, 'group_type' => reset($types)]);
        }
        $label = $year ?: 'vie entière';
        Audit::log('compute', 'index', null, "Calcul des indices ($label) : " . implode(', ', $msg));
        flash('success', sprintf('Indices %s calculés et enregistrés — %s (%.2f s).', $label, implode(' ; ', $msg), microtime(true) - $t));
        redirect($redirect);
    }

    private function buildQuery(): KeysetPaginator
    {
        $where = [];
        $p = [];
        $g = $_GET;
        if (isset($g['year']) && $g['year'] !== '' && is_numeric($g['year'])) {
            $where[] = 'r.year = :y';
            $p['y'] = (int) $g['year'];
        }
        if (!empty($g['group_type']) && in_array($g['group_type'], array_merge(self::GROUP_TYPES, ['animal']), true)) {
            $where[] = 'r.group_type = :gt';
            $p['gt'] = $g['group_type'];
        }
        if (!empty($g['index_id']) && ctype_digit((string) $g['index_id'])) {
            $where[] = 'r.index_id = :iid';
            $p['iid'] = (int) $g['index_id'];
        }
        if (!empty($g['level']) && isset(labels('level')[$g['level']])) {
            $where[] = 'r.level = :lvl';
            $p['lvl'] = $g['level'];
        }
        if (($q = trim((string) ($g['q'] ?? ''))) !== '') {
            $where[] = 'r.group_label LIKE :q';
            $p['q'] = $q . '%';
        }
        if (!empty($g['animal_id']) && ctype_digit((string) $g['animal_id'])) {
            $where[] = 'r.animal_id = :aid';
            $p['aid'] = (int) $g['animal_id'];
        }
        $sort = ($g['sort'] ?? '') === 'value' ? ['r.value', 'value'] : ['r.id', 'id'];
        if ($sort[0] === 'r.value') {
            $where[] = 'r.value IS NOT NULL';
        }
        $dir = strtolower((string) ($g['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        return new KeysetPaginator(
            'r.*, d.name, d.unit, d.decimals, d.code',
            'FROM index_results r JOIN index_definitions d ON d.id = r.index_id',
            $where, $p, $sort[0], $sort[1], $dir, $this->perPage(), 'r.id', 'id'
        );
    }

    public function results(): never
    {
        $this->authorize('indices.view');
        $pager = $this->buildQuery();
        $page = $pager->paginate($_GET['cursor'] ?? null, ($_GET['dir_page'] ?? 'after') === 'before' ? 'before' : 'after');
        $this->render('indices/results', [
            'page' => $page, 'total' => $pager->count('indices'), 'years' => $this->years(),
            'defs' => Database::all('SELECT id, name, scope FROM index_definitions ORDER BY scope, sort_order'),
        ]);
    }

    public function show(): never
    {
        $this->authorize('indices.view');
        $r = Database::one(
            'SELECT r.*, d.name, d.unit, d.decimals, d.description, d.scope, d.formula AS current_formula, d.threshold_low, d.threshold_high, d.better,
                    u.full_name AS computed_by_name
             FROM index_results r JOIN index_definitions d ON d.id = r.index_id LEFT JOIN users u ON u.id = r.computed_by WHERE r.id = :id',
            ['id' => query_int('id')]
        ) ?? abort(404, 'Résultat introuvable.');
        $catalog = IndexCalculator::variablesFor($r['scope']);
        $this->render('indices/show', ['r' => $r, 'vars' => json_decode((string) $r['variables'], true) ?: [], 'catalog' => $catalog]);
    }

    private function evolutionData(string $groupType, string $groupValue): array
    {
        $rows = Database::all(
            'SELECT r.year, r.value, r.level, d.id, d.name, d.unit, d.decimals FROM index_results r
             JOIN index_definitions d ON d.id = r.index_id
             WHERE r.group_type = :g AND r.group_value = :v AND r.year > 0 ORDER BY d.sort_order, r.year',
            ['g' => $groupType, 'v' => $groupValue]
        );
        $years = [];
        $matrix = [];
        foreach ($rows as $r) {
            $years[$r['year']] = true;
            $matrix[$r['id']]['def'] = $r;
            $matrix[$r['id']]['values'][$r['year']] = $r;
        }
        $years = array_keys($years);
        sort($years);
        return [$years, $matrix];
    }

    public function evolution(): never
    {
        $this->authorize('indices.view');
        $groupType = in_array($_GET['group_type'] ?? '', self::GROUP_TYPES, true) ? $_GET['group_type'] : 'global';
        $groups = IndexCalculator::groups($groupType);
        $groupValue = (string) ($_GET['group_value'] ?? array_key_first($groups));
        [$years, $matrix] = $this->evolutionData($groupType, $groupValue);
        $this->render('indices/evolution', compact('groupType', 'groups', 'groupValue', 'years', 'matrix'));
    }

    public function export(): never
    {
        $this->authorize('indices.export');
        if (($_GET['mode'] ?? '') === 'evolution') {
            $groupType = in_array($_GET['group_type'] ?? '', self::GROUP_TYPES, true) ? $_GET['group_type'] : 'global';
            $groups = IndexCalculator::groups($groupType);
            $groupValue = (string) ($_GET['group_value'] ?? array_key_first($groups));
            [$years, $matrix] = $this->evolutionData($groupType, $groupValue);
            $pdf = new PdfReport('Évolution des indices', label('group_type', $groupType) . ' : ' . ($groups[$groupValue] ?? ''), 'L');
            $pdf->addPage();
            $cols = [['label' => 'Indice', 'key' => 'name', 'w' => 3]];
            foreach ($years as $y) {
                $cols[] = ['label' => (string) $y, 'key' => "y$y", 'w' => 1, 'align' => 'R'];
            }
            $rows = [];
            foreach ($matrix as $m) {
                $row = ['name' => $m['def']['name'] . ($m['def']['unit'] ? ' (' . $m['def']['unit'] . ')' : '')];
                foreach ($years as $y) {
                    $c = $m['values'][$y] ?? null;
                    $row["y$y"] = $c && $c['value'] !== null ? fmt_num($c['value'], (int) $m['def']['decimals']) . ' [' . mb_substr(label('level', $c['level']), 0, 1) . ']' : '—';
                }
                $rows[] = $row;
            }
            $pdf->table($cols, $rows, 8);
            $pdf->setFont(false, 7.5);
            $pdf->paragraph('[B] Bon  [M] Moyen  [F] Faible  [N] Non déterminé', 4);
            Audit::log('export', 'index', null, 'Export PDF évolution des indices');
            $pdf->send('evolution_indices.pdf', !isset($_GET['download']));
        }

        if (($_GET['mode'] ?? '') === 'overview') {
            $year = (int) ($_GET['year'] ?? date('Y'));
            $groupType = in_array($_GET['group_type'] ?? '', self::GROUP_TYPES, true) ? $_GET['group_type'] : 'global';
            $_GET = ['year' => (string) $year, 'group_type' => $groupType, 'sort' => 'id', 'dir' => 'asc'];
        }
        $rows = $this->buildQuery()->all();
        Audit::log('export', 'index', null, 'Export PDF des résultats d\'indices (' . count($rows) . ' lignes)');
        $pdf = new PdfReport('Résultats des indices', $this->filterSummary([
            'year' => 'Année', 'group_type' => 'Regroupement', 'index_id' => 'Indice n°', 'level' => 'Niveau', 'q' => 'Groupe / animal',
        ]), 'L');
        $pdf->addPage();
        $pdf->table([
            ['label' => 'Année', 'key' => 'year', 'w' => 0.6],
            ['label' => 'Groupe / animal', 'key' => 'group', 'w' => 1.8],
            ['label' => 'Indice', 'key' => 'name', 'w' => 2],
            ['label' => 'Valeur', 'key' => 'value', 'w' => 1, 'align' => 'R'],
            ['label' => 'Niveau', 'key' => 'level', 'w' => 0.7],
            ['label' => 'Interprétation', 'key' => 'interpretation', 'w' => 5],
        ], array_map(static fn($r) => [
            'year' => $r['year'] ?: 'Vie', 'group' => label('group_type', $r['group_type']) . ' : ' . $r['group_label'], 'name' => $r['name'],
            'value' => $r['value'] !== null ? fmt_num($r['value'], (int) $r['decimals']) . ' ' . $r['unit'] : 'N/D',
            'level' => label('level', $r['level']), 'interpretation' => $r['interpretation'],
        ], $rows), 7.5);
        $pdf->send('indices_' . date('Ymd_His') . '.pdf', !isset($_GET['download']));
    }

    public function delete(): never
    {
        $this->authorize('indices.delete');
        if (!empty($_POST['id'])) {
            $old = Database::find('index_results', (int) $_POST['id']) ?? abort(404);
            Database::delete('index_results', (int) $_POST['id']);
            Audit::log('delete', 'index_result', (int) $_POST['id'], 'Suppression d\'un résultat d\'indice', $old, null);
            $n = 1;
        } else {
            $year = (int) ($_POST['year'] ?? -1);
            $gt = (string) ($_POST['group_type'] ?? '');
            $n = Database::query('DELETE FROM index_results WHERE year = :y AND group_type = :g', ['y' => $year, 'g' => $gt])->rowCount();
            Audit::log('delete', 'index_result', null, "Purge des résultats : année $year, " . label('group_type', $gt) . " ($n lignes)");
        }
        Cache::bump('indices');
        flash('success', "$n résultat(s) supprimé(s).");
        back(url('indices/results'));
    }
}
