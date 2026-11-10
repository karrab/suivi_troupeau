<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\History;
use App\Core\KeysetPaginator;
use App\Core\Pdf\PdfReport;
use App\Core\Validator;
use App\Services\Ref;
use App\Services\Tracking;

/**
 * Contrôleur générique des suivis : pesées, santé, saillies, mises bas,
 * lait, mouvements (voir app/Config/tracking.php).
 */
final class TrackingController extends Controller
{
    private array $cfg;
    private string $type;

    public function __construct()
    {
        $this->type = (string) ($_GET['type'] ?? $_POST['type'] ?? '');
        $this->cfg = Tracking::get($this->type);
    }

    private function perm(string $action): void
    {
        $this->authorize($this->type . '.' . $action);
    }

    // ----------------------------------------------------------- liste
    private function buildQuery(): KeysetPaginator
    {
        [$select, $from] = Tracking::sql($this->cfg);
        $where = [];
        $p = [];
        $g = $_GET;
        if (($q = trim((string) ($g['animal'] ?? ''))) !== '') {
            $where[] = '(a.tag LIKE :aq1 OR a.name LIKE :aq2)';
            $p['aq1'] = $q . '%';
            $p['aq2'] = $q . '%';
        }
        if (!empty($g['animal_id']) && ctype_digit((string) $g['animal_id'])) {
            $where[] = 't.animal_id = :aid';
            $p['aid'] = (int) $g['animal_id'];
        }
        if (!empty($g['from']) && Validator::isDate((string) $g['from'])) {
            $where[] = 't.event_date >= :from';
            $p['from'] = $g['from'];
        }
        if (!empty($g['to']) && Validator::isDate((string) $g['to'])) {
            $where[] = 't.event_date <= :to';
            $p['to'] = $g['to'];
        }
        if (!empty($g['species']) && isset(labels('species')[$g['species']])) {
            $where[] = 'a.species = :species';
            $p['species'] = $g['species'];
        }
        if (!empty($g['lot_id']) && ctype_digit((string) $g['lot_id'])) {
            $where[] = 'a.lot_id = :lot';
            $p['lot'] = (int) $g['lot_id'];
        }
        foreach ($this->cfg['filters'] as $f) {
            $opts = labels($this->cfg['fields'][$f]['options']);
            if (!empty($g[$f]) && isset($opts[$g[$f]])) {
                $where[] = "t.$f = :f_$f";
                $p["f_$f"] = $g[$f];
            }
        }
        if ($this->cfg['search'] && ($s = trim((string) ($g['q'] ?? ''))) !== '') {
            $or = [];
            foreach ($this->cfg['search'] as $i => $f) {
                $or[] = "t.$f LIKE :s$i";
                $p["s$i"] = '%' . $s . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        $dir = strtolower((string) ($g['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        return new KeysetPaginator($select, $from, $where, $p, 't.event_date', 'event_date', $dir, $this->perPage(), 't.id', 'id');
    }

    public function index(): never
    {
        $this->perm('view');
        $pager = $this->buildQuery();
        $page = $pager->paginate($_GET['cursor'] ?? null, ($_GET['dir_page'] ?? 'after') === 'before' ? 'before' : 'after');
        $this->render('tracking/index', [
            'cfg' => $this->cfg, 'type' => $this->type, 'page' => $page,
            'total' => $pager->count('tracking'), 'lots' => Ref::lots(),
        ]);
    }

    public function export(): never
    {
        $this->perm('export');
        $rows = $this->buildQuery()->all();
        Audit::log('export', $this->type, null, 'Export PDF : ' . $this->cfg['title'] . ' (' . count($rows) . ' lignes)');
        $labels = ['animal' => 'Animal', 'from' => 'Du', 'to' => 'Au', 'species' => 'Espèce', 'q' => 'Recherche'];
        foreach ($this->cfg['filters'] as $f) {
            $labels[$f] = $this->cfg['fields'][$f]['label'];
        }
        $pdf = new PdfReport($this->cfg['title'], $this->filterSummary($labels), 'L');
        $pdf->addPage();
        $cols = [];
        foreach ($this->cfg['list'] as $f) {
            $def = $this->cfg['fields'][$f];
            $cols[] = ['label' => $def['label'], 'key' => $f, 'w' => in_array($f, ['notes', 'diagnosis', 'animal_id'], true) ? 1.8 : 1,
                'align' => $def['type'] === 'number' ? 'R' : 'L'];
        }
        $pdf->table($cols, array_map(function ($r) {
            $o = [];
            foreach ($this->cfg['list'] as $f) {
                $o[$f] = Tracking::format($this->cfg, $f, $r, false);
            }
            return $o;
        }, $rows));
        $pdf->send($this->type . '_' . date('Ymd_His') . '.pdf', !isset($_GET['download']));
    }

    /** PDF d'un enregistrement (fiche d'intervention). */
    public function pdf(): never
    {
        $this->perm('export');
        $row = $this->load(query_int('id'));
        Audit::log('export', $this->type, (int) $row['id'], 'PDF : ' . $this->cfg['singular'] . ' #' . $row['id']);
        $pdf = new PdfReport(ucfirst($this->cfg['singular']) . ' n° ' . $row['id'], 'Animal : ' . $row['animal_tag']);
        $pdf->addPage();
        $pdf->sectionTitle(ucfirst($this->cfg['singular']));
        $pairs = [];
        foreach ($this->cfg['fields'] as $f => $def) {
            if ($def['type'] !== 'virtual') {
                $pairs[$def['label']] = Tracking::format($this->cfg, $f, $row, false) ?: '—';
            }
        }
        $pdf->keyValues($pairs, 1);
        $pdf->setFont(false, 8);
        $pdf->paragraph('Saisi le ' . fmt_date($row['created_at'], true) . ($row['updated_at'] ? ' — modifié le ' . fmt_date($row['updated_at'], true) : ''), 4.5);
        $pdf->send($this->type . '_' . $row['id'] . '.pdf');
    }

    // ------------------------------------------------------------ CRUD
    private function load(int $id): array
    {
        [$select, $from] = Tracking::sql($this->cfg);
        return Database::one("SELECT $select $from WHERE t.id = :id", ['id' => $id]) ?? abort(404, 'Enregistrement introuvable.');
    }

    public function create(): never
    {
        $this->perm('create');
        $row = ['event_date' => date('Y-m-d')];
        foreach ($this->cfg['fields'] as $f => $def) {
            if (isset($def['default'])) {
                $row[$f] = $def['default'];
            }
        }
        if (($aid = query_int('animal_id')) > 0) {
            $row['animal_id'] = $aid;
            $row['animal_tag'] = Database::value('SELECT tag FROM animals WHERE id = :id', ['id' => $aid]);
        }
        $this->renderForm($row, false);
    }

    public function edit(): never
    {
        $this->perm('edit');
        $this->renderForm($this->load(query_int('id')), true);
    }

    private function renderForm(array $row, bool $isEdit): never
    {
        $this->render('tracking/form', [
            'cfg' => $this->cfg, 'type' => $this->type, 'row' => $row, 'isEdit' => $isEdit,
            'errors' => form_errors(), 'lots' => Ref::lots(),
        ]);
    }

    private function validate(): array
    {
        $rules = [];
        $labels = [];
        foreach ($this->cfg['fields'] as $f => $def) {
            $labels[$f] = $def['label'];
            if ($def['type'] === 'virtual') {
                continue;
            }
            $r = [];
            if (!empty($def['required']) && $def['type'] !== 'animal') {
                $r[] = 'required';
            }
            $r = array_merge($r, match ($def['type']) {
                'date'   => ['date'],
                'number' => array_filter(['numeric', isset($def['min']) ? 'min:' . $def['min'] : null, isset($def['max']) ? 'max:' . $def['max'] : null]),
                'select' => ['in:' . implode(',', array_keys(labels($def['options'])))],
                'lot'    => ['int'],
                'bool'   => ['bool'],
                'text', 'textarea' => isset($def['maxlen']) ? ['maxlen:' . $def['maxlen']] : [],
                default  => [],
            });
            if ($def['type'] !== 'animal') {
                $rules[$f] = $r;
            }
        }
        $v = Validator::make($_POST, $rules, $labels);
        $d = $v->data();

        // Champs animaux saisis par n° de boucle
        $main = null;
        foreach ($this->cfg['fields'] as $f => $def) {
            if ($def['type'] !== 'animal') {
                continue;
            }
            $tag = trim((string) ($_POST[$f . '_tag'] ?? ''));
            $d[$f] = null;
            if ($tag === '') {
                if (!empty($def['required'])) {
                    $v->addError($f, "Le champ « {$def['label']} » est obligatoire.");
                }
                continue;
            }
            $an = Database::one('SELECT id, tag, sex, species, birth_date, lot_id FROM animals WHERE tag = :t', ['t' => $tag]);
            if (!$an) {
                $v->addError($f, "Animal introuvable : $tag");
                continue;
            }
            if (!empty($def['sex']) && $an['sex'] !== $def['sex']) {
                $v->addError($f, "« {$def['label']} » : l'animal $tag doit être " . ($def['sex'] === 'F' ? 'une femelle.' : 'un mâle.'));
                continue;
            }
            if ($f === 'animal_id') {
                $main = $an;
            } elseif ($main && $an['species'] !== $main['species']) {
                $v->addError($f, "« {$def['label']} » doit être de la même espèce que l'animal.");
                continue;
            }
            $d[$f] = (int) $an['id'];
        }
        if ($main && !empty($d['event_date'])) {
            if ($d['event_date'] < $main['birth_date']) {
                $v->addError('event_date', 'La date est antérieure à la naissance de l\'animal.');
            }
            if ($d['event_date'] > date('Y-m-d', strtotime('+1 day')) && $this->type !== 'matings') {
                $v->addError('event_date', 'La date ne peut pas être dans le futur.');
            }
        }
        foreach ($this->cfg['fields'] as $f => $def) {
            if ($def['type'] === 'lot') {
                $d[$f] = $d[$f] ? (int) $d[$f] : null;
            }
            if (in_array($def['type'], ['text', 'textarea'], true) && ($d[$f] ?? '') === '') {
                $d[$f] = null;
            }
        }

        // Règles spécifiques
        if ($this->type === 'births') {
            $d['abortion'] = (int) ($d['abortion'] ?? 0);
            if ($d['abortion']) {
                $d['born_total'] = $d['born_alive'] = $d['born_males'] = $d['born_females'] = 0;
            } else {
                if ((int) $d['born_alive'] > (int) $d['born_total']) {
                    $v->addError('born_alive', 'Les nés vivants ne peuvent pas dépasser le total.');
                }
                if ((int) $d['born_males'] + (int) $d['born_females'] > (int) $d['born_total']) {
                    $v->addError('born_males', 'Mâles + femelles ne peuvent pas dépasser le total.');
                }
            }
        }
        if ($this->type === 'matings' && $main && empty($d['expected_birth']) && !empty($d['event_date']) && $d['diagnosis'] !== 'vide') {
            $days = (int) setting($main['species'] === 'caprin' ? 'gestation_caprin_jours' : 'gestation_ovin_jours', 150);
            $d['expected_birth'] = date('Y-m-d', strtotime($d['event_date'] . " +$days days"));
        }
        if ($this->type === 'movements' && $main && empty($d['from_lot_id'])) {
            $d['from_lot_id'] = $main['lot_id'] ? (int) $main['lot_id'] : null;
        }
        foreach ($this->cfg['fields'] as $f => $def) {
            if ($def['type'] === 'virtual') {
                unset($d[$f]);
            }
        }
        return [$d, $v->errors(), $main];
    }

    public function store(): never
    {
        $this->perm('create');
        [$d, $errors, $main] = $this->validate();
        if ($errors) {
            with_input($_POST, $errors);
            redirect(url('tracking/create', ['type' => $this->type]));
        }
        $d['created_by'] = Auth::id();
        $created = 0;
        $id = Database::transaction(function () use ($d, $main, &$created) {
            $id = Database::insert($this->cfg['table'], $d);
            $row = $this->load($id);
            History::add((int) $d['animal_id'], $d['event_date'], $this->cfg['history_type'], Tracking::summary($this->cfg, $row), $this->cfg['table'], $id);
            // Mâle / père : l'événement apparaît aussi dans son historique
            foreach (['male_id', 'father_id'] as $f) {
                if (!empty($d[$f])) {
                    History::add((int) $d[$f], $d['event_date'], $this->cfg['history_type'],
                        Tracking::summary($this->cfg, $row) . ' — femelle ' . $row['animal_tag'], $this->cfg['table'], $id);
                }
            }
            $created = $this->afterSave($d, $id, $main);
            return $id;
        });
        Audit::log('create', $this->type, $id, ucfirst($this->cfg['singular']) . ' pour ' . $main['tag'], null, $d);
        Cache::bump('tracking', 'animals');
        flash('success', ucfirst($this->cfg['singular']) . ' enregistré(e).' . ($created ? " $created fiche(s) de nouveau-né créée(s)." : ''));
        $back = $_POST['back'] ?? '';
        if (isset($_POST['save_new'])) {
            redirect(url('tracking/create', ['type' => $this->type]));
        }
        redirect($back === 'animal' ? url('animals/show', ['id' => $d['animal_id'], 'tab' => $this->type]) : url('tracking', ['type' => $this->type]));
    }

    /** Effets de bord métier après création. Retourne le nombre de jeunes créés. */
    private function afterSave(array $d, int $id, array $main): int
    {
        if ($this->type === 'movements' && array_key_exists('to_lot_id', $d) && $d['to_lot_id']) {
            Database::query('UPDATE animals SET lot_id = :l, updated_by = :u WHERE id = :id', ['l' => $d['to_lot_id'], 'u' => Auth::id(), 'id' => $d['animal_id']]);
        }
        if ($this->type === 'weighings' && ($d['context'] ?? '') === 'naissance') {
            Database::query('UPDATE animals SET birth_weight = :w WHERE id = :id AND birth_weight IS NULL', ['w' => $d['weight'], 'id' => $d['animal_id']]);
        }
        if ($this->type === 'health' && ($d['outcome'] ?? '') === 'mort') {
            Database::query("UPDATE animals SET status = 'mort', exit_date = :d, exit_reason = :r WHERE id = :id AND status = 'actif'",
                ['d' => $d['event_date'], 'r' => mb_substr('Maladie : ' . ($d['diagnosis'] ?? ''), 0, 160), 'id' => $d['animal_id']]);
            History::add((int) $d['animal_id'], $d['event_date'], 'sortie', 'Sortie : mort (suivi sanitaire)');
        }
        if ($this->type === 'births' && !empty($_POST['create_offspring']) && !(int) $d['abortion'] && (int) $d['born_alive'] > 0) {
            return $this->createOffspring($d, $id, $main);
        }
        return 0;
    }

    private function createOffspring(array $d, int $birthId, array $mother): int
    {
        $cats = Database::pairs('SELECT code, id FROM categories');
        $alive = (int) $d['born_alive'];
        $males = min((int) $d['born_males'], $alive);
        $types = [1 => 'simple', 2 => 'double', 3 => 'triple'];
        $nursery = Database::value("SELECT id FROM lots WHERE name = 'Nurserie'");
        $base = $mother['tag'] . '-' . date('ymd', strtotime($d['event_date']));
        for ($i = 1; $i <= $alive; $i++) {
            $sex = $i <= $males ? 'M' : 'F';
            $code = $mother['species'] === 'caprin' ? ($sex === 'M' ? 'chevreau' : 'chevrette') : ($sex === 'M' ? 'agneau' : 'agnelle');
            $tag = $base . '-' . $i;
            $n = 1;
            while (Database::value('SELECT 1 FROM animals WHERE tag = :t', ['t' => $tag])) {
                $tag = $base . '-' . $i . chr(96 + ++$n);
            }
            $kid = Database::insert('animals', [
                'tag' => mb_substr($tag, 0, 30), 'species' => $mother['species'], 'category_id' => (int) $cats[$code], 'sex' => $sex,
                'birth_date' => $d['event_date'], 'birth_type' => $types[min(3, (int) $d['born_total'])] ?? 'quadruple+',
                'mother_id' => (int) $d['animal_id'], 'father_id' => $d['father_id'] ?? null, 'origin' => 'naissance',
                'entry_date' => $d['event_date'], 'lot_id' => $nursery ? (int) $nursery : null, 'created_by' => Auth::id(),
                'breed_id' => Database::value('SELECT breed_id FROM animals WHERE id = :id', ['id' => (int) $d['animal_id']]),
            ]);
            History::add($kid, $d['event_date'], 'naissance', 'Né(e) de ' . $mother['tag'] . ' (mise bas n° ' . $birthId . ')', 'births', $birthId);
            Audit::log('create', 'animal', $kid, 'Création automatique du nouveau-né ' . $tag);
        }
        return $alive;
    }

    public function update(): never
    {
        $this->perm('edit');
        $id = (int) ($_POST['id'] ?? 0);
        $old = Database::find($this->cfg['table'], $id) ?? abort(404, 'Enregistrement introuvable.');
        [$d, $errors] = $this->validate();
        if ($errors) {
            with_input($_POST, $errors);
            redirect(url('tracking/edit', ['type' => $this->type, 'id' => $id]));
        }
        Database::transaction(function () use ($id, $d) {
            Database::update($this->cfg['table'], $d, $id);
            $row = $this->load($id);
            History::syncRef($this->cfg['table'], $id, (int) $d['animal_id'], $d['event_date'], Tracking::summary($this->cfg, $row) . ' (modifié)');
        });
        Audit::log('update', $this->type, $id, ucfirst($this->cfg['singular']) . ' #' . $id, $old, $d);
        Cache::bump('tracking');
        flash('success', 'Modification enregistrée.');
        redirect(($_POST['back'] ?? '') === 'animal' ? url('animals/show', ['id' => $d['animal_id'], 'tab' => $this->type]) : url('tracking', ['type' => $this->type]));
    }

    public function delete(): never
    {
        $this->perm('delete');
        $id = (int) ($_POST['id'] ?? 0);
        $old = $this->load($id);
        Database::transaction(function () use ($id, $old) {
            Database::delete($this->cfg['table'], $id);
            Database::query('DELETE FROM animal_history WHERE ref_table = :t AND ref_id = :r AND event_type <> :n',
                ['t' => $this->cfg['table'], 'r' => $id, 'n' => 'naissance']);
            History::add((int) $old['animal_id'], date('Y-m-d'), 'suppression',
                'Suppression : ' . Tracking::summary($this->cfg, $old) . ' du ' . fmt_date($old['event_date']), $this->cfg['table'], $id);
        });
        $snapshot = array_filter($old, static fn($k) => !str_ends_with((string) $k, '_tag') && !str_ends_with((string) $k, '_name') && $k !== 'animal_species', ARRAY_FILTER_USE_KEY);
        Audit::log('delete', $this->type, $id, ucfirst($this->cfg['singular']) . ' #' . $id . ' (' . $old['animal_tag'] . ')', $snapshot, null);
        Cache::bump('tracking');
        flash('success', 'Enregistrement supprimé.');
        ($_POST['back'] ?? '') === 'animal' ? redirect(url('animals/show', ['id' => $old['animal_id'], 'tab' => $this->type])) : back(url('tracking', ['type' => $this->type]));
    }
}
