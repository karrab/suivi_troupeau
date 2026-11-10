<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Database;
use App\Core\KeysetPaginator;
use App\Core\Pdf\PdfReport;
use App\Core\Validator;

final class AuditController extends Controller
{
    private function buildQuery(): KeysetPaginator
    {
        $where = [];
        $p = [];
        $g = $_GET;
        if (!empty($g['user_id']) && ctype_digit((string) $g['user_id'])) {
            $where[] = 'l.user_id = :u';
            $p['u'] = (int) $g['user_id'];
        }
        if (!empty($g['action']) && isset(labels('audit_action')[$g['action']])) {
            $where[] = 'l.action = :a';
            $p['a'] = $g['action'];
        }
        if (!empty($g['entity']) && preg_match('/^\w+$/', (string) $g['entity'])) {
            $where[] = 'l.entity = :e';
            $p['e'] = $g['entity'];
        }
        if (!empty($g['entity_id']) && ctype_digit((string) $g['entity_id'])) {
            $where[] = 'l.entity_id = :eid';
            $p['eid'] = (int) $g['entity_id'];
        }
        if (!empty($g['from']) && Validator::isDate((string) $g['from'])) {
            $where[] = 'l.created_at >= :from';
            $p['from'] = $g['from'] . ' 00:00:00';
        }
        if (!empty($g['to']) && Validator::isDate((string) $g['to'])) {
            $where[] = 'l.created_at <= :to';
            $p['to'] = $g['to'] . ' 23:59:59';
        }
        if (($q = trim((string) ($g['q'] ?? ''))) !== '') {
            $where[] = 'l.summary LIKE :q';
            $p['q'] = '%' . $q . '%';
        }
        return new KeysetPaginator('l.*', 'FROM audit_logs l', $where, $p, 'l.id', 'id', 'DESC', $this->perPage(), 'l.id', 'id');
    }

    public function index(): never
    {
        $this->authorize('audit.view');
        $pager = $this->buildQuery();
        $page = $pager->paginate($_GET['cursor'] ?? null, ($_GET['dir_page'] ?? 'after') === 'before' ? 'before' : 'after');
        $this->render('audit/index', [
            'page' => $page,
            'users' => Database::pairs('SELECT id, username FROM users ORDER BY username'),
            'entities' => Database::query('SELECT DISTINCT entity FROM audit_logs ORDER BY entity')->fetchAll(\PDO::FETCH_COLUMN),
        ]);
    }

    public function show(): never
    {
        $this->authorize('audit.view');
        $log = Database::find('audit_logs', query_int('id')) ?? abort(404);
        $this->render('audit/show', [
            'log' => $log,
            'old' => json_decode((string) $log['old_values'], true) ?: [],
            'new' => json_decode((string) $log['new_values'], true) ?: [],
        ]);
    }

    public function export(): never
    {
        $this->authorize('audit.export');
        $rows = $this->buildQuery()->all();
        Audit::log('export', 'audit', null, 'Export PDF du journal des traces (' . count($rows) . ' lignes)');
        $pdf = new PdfReport('Journal des traces', $this->filterSummary([
            'user_id' => 'Utilisateur n°', 'action' => 'Action', 'entity' => 'Objet', 'from' => 'Du', 'to' => 'Au', 'q' => 'Recherche',
        ]), 'L');
        $pdf->addPage();
        $pdf->table([
            ['label' => 'Date', 'key' => 'date', 'w' => 1.1], ['label' => 'Utilisateur', 'key' => 'username', 'w' => 0.9],
            ['label' => 'Action', 'key' => 'action', 'w' => 0.9], ['label' => 'Objet', 'key' => 'entity', 'w' => 1.1],
            ['label' => 'Description', 'key' => 'summary', 'w' => 4.5], ['label' => 'IP', 'key' => 'ip_address', 'w' => 0.9],
        ], array_map(static fn($r) => [
            'date' => fmt_date($r['created_at'], true), 'username' => $r['username'], 'action' => label('audit_action', $r['action']),
            'entity' => $r['entity'] . ($r['entity_id'] ? ' #' . $r['entity_id'] : ''), 'summary' => $r['summary'], 'ip_address' => $r['ip_address'],
        ], $rows), 7.5);
        $pdf->send('traces_' . date('Ymd_His') . '.pdf', !isset($_GET['download']));
    }
}
