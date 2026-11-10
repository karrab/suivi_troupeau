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
use App\Services\IndexCalculator;
use App\Services\Ref;
use App\Services\Tracking;

final class AnimalController extends Controller
{
    private const SORTS = [
        'id'         => ['a.id', 'id'],
        'tag'        => ['a.tag', 'tag'],
        'birth_date' => ['a.birth_date', 'birth_date'],
        'entry_date' => ['a.entry_date', 'entry_date'],
    ];

    private const FIELD_LABELS = [
        'tag' => 'N° boucle', 'name' => 'Nom', 'category_id' => 'Catégorie', 'breed_id' => 'Race', 'lot_id' => 'Lot',
        'birth_date' => 'Date de naissance', 'birth_date_estimated' => 'Date estimée', 'birth_weight' => 'Poids de naissance',
        'birth_type' => 'Type de naissance', 'mother_id' => 'Mère', 'father_id' => 'Père', 'origin' => 'Origine',
        'entry_date' => 'Date d\'entrée', 'purchase_price' => 'Prix d\'achat', 'color' => 'Robe', 'status' => 'Statut',
        'exit_date' => 'Date de sortie', 'exit_reason' => 'Motif de sortie', 'sale_price' => 'Prix de vente', 'notes' => 'Remarques',
        'species' => 'Espèce', 'sex' => 'Sexe',
    ];

    // =================================================================
    //  Recherche avancée (filtres -> WHERE indexés)
    // =================================================================
    private function buildQuery(): KeysetPaginator
    {
        $where = [];
        $p = [];
        $g = $_GET;

        if (($q = trim((string) ($g['q'] ?? ''))) !== '') {
            $where[] = '(a.tag LIKE :q1 OR a.name LIKE :q2)';
            $p['q1'] = $q . '%';
            $p['q2'] = '%' . $q . '%';
        }
        $status = $g['status'] ?? 'actif';
        if ($status !== '' && $status !== 'tous' && isset(labels('status')[$status])) {
            $where[] = 'a.status = :status';
            $p['status'] = $status;
        }
        foreach (['species' => 'species', 'sex' => 'sex', 'origin' => 'origin'] as $k => $col) {
            if (!empty($g[$k]) && isset(labels($k)[$g[$k]])) {
                $where[] = "a.$col = :$k";
                $p[$k] = $g[$k];
            }
        }
        foreach (['category_id', 'breed_id', 'lot_id'] as $k) {
            if (!empty($g[$k]) && ctype_digit((string) $g[$k])) {
                $where[] = "a.$k = :$k";
                $p[$k] = (int) $g[$k];
            }
        }
        foreach (['birth_from' => ['a.birth_date', '>='], 'birth_to' => ['a.birth_date', '<='],
                  'entry_from' => ['a.entry_date', '>='], 'entry_to' => ['a.entry_date', '<='],
                  'exit_from' => ['a.exit_date', '>='], 'exit_to' => ['a.exit_date', '<=']] as $k => [$col, $op]) {
            if (!empty($g[$k]) && Validator::isDate((string) $g[$k])) {
                $where[] = "$col $op :$k";
                $p[$k] = $g[$k];
            }
        }
        if (isset($g['age_min']) && $g['age_min'] !== '' && is_numeric($g['age_min'])) {
            $where[] = 'a.birth_date <= DATE_SUB(CURDATE(), INTERVAL :age_min MONTH)';
            $p['age_min'] = (int) $g['age_min'];
        }
        if (isset($g['age_max']) && $g['age_max'] !== '' && is_numeric($g['age_max'])) {
            $where[] = 'a.birth_date >= DATE_SUB(CURDATE(), INTERVAL :age_max MONTH)';
            $p['age_max'] = (int) $g['age_max'];
        }
        foreach (['mother' => 'mother_id', 'father' => 'father_id'] as $k => $col) {
            if (!empty($g[$k])) {
                $where[] = "a.$col IN (SELECT x.id FROM animals x WHERE x.tag LIKE :$k)";
                $p[$k] = trim((string) $g[$k]) . '%';
            }
        }

        [$sortExpr, $sortKey] = self::SORTS[$g['sort'] ?? 'id'] ?? self::SORTS['id'];
        $dir = strtolower((string) ($g['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $select = 'a.*, c.label AS category_label, b.name AS breed_name, l.name AS lot_name,
            (SELECT w.weight FROM weighings w WHERE w.animal_id = a.id ORDER BY w.event_date DESC, w.id DESC LIMIT 1) AS last_weight';
        $from = 'FROM animals a JOIN categories c ON c.id = a.category_id
                 LEFT JOIN breeds b ON b.id = a.breed_id LEFT JOIN lots l ON l.id = a.lot_id';

        return new KeysetPaginator($select, $from, $where, $p, $sortExpr, $sortKey, $dir, $this->perPage(), 'a.id', 'id');
    }

    public function index(): never
    {
        $this->authorize('animals.view');
        $pager = $this->buildQuery();
        $page = $pager->paginate($_GET['cursor'] ?? null, ($_GET['dir_page'] ?? 'after') === 'before' ? 'before' : 'after');
        $total = $pager->count('animals');
        $this->render('animals/index', [
            'page' => $page, 'total' => $total,
            'categories' => Ref::categories(), 'breeds' => Ref::breeds(), 'lots' => Ref::lots(),
        ]);
    }

    public function export(): never
    {
        $this->authorize('animals.export');
        $rows = $this->buildQuery()->all();
        Audit::log('export', 'animal', null, 'Export PDF de la liste des animaux (' . count($rows) . ' lignes)');
        $pdf = new PdfReport('Liste des animaux', $this->filterSummary([
            'q' => 'Recherche', 'status' => 'Statut', 'species' => 'Espèce', 'sex' => 'Sexe', 'category_id' => 'Catégorie',
            'breed_id' => 'Race', 'lot_id' => 'Lot', 'birth_from' => 'Né après', 'birth_to' => 'Né avant',
            'age_min' => 'Âge min (mois)', 'age_max' => 'Âge max (mois)', 'mother' => 'Mère', 'father' => 'Père',
        ]), 'L');
        $pdf->addPage();
        $pdf->table([
            ['label' => 'N° boucle', 'key' => 'tag', 'w' => 1.4],
            ['label' => 'Nom', 'key' => 'name', 'w' => 1.3],
            ['label' => 'Catégorie', 'key' => 'category_label', 'w' => 1.3],
            ['label' => 'Sexe', 'key' => 'sex', 'w' => 0.5, 'align' => 'C'],
            ['label' => 'Race', 'key' => 'breed_name', 'w' => 1.1],
            ['label' => 'Lot', 'key' => 'lot_name', 'w' => 1.1],
            ['label' => 'Naissance', 'key' => 'birth', 'w' => 0.9],
            ['label' => 'Âge', 'key' => 'age', 'w' => 0.9],
            ['label' => 'Origine', 'key' => 'origin', 'w' => 1.2],
            ['label' => 'Dern. poids', 'key' => 'last_weight', 'w' => 0.8, 'align' => 'R'],
            ['label' => 'Statut', 'key' => 'status', 'w' => 0.7],
            ['label' => 'Sortie', 'key' => 'exit', 'w' => 0.9],
        ], array_map(static fn($r) => [
            'tag' => $r['tag'], 'name' => $r['name'], 'category_label' => $r['category_label'], 'sex' => $r['sex'],
            'breed_name' => $r['breed_name'], 'lot_name' => $r['lot_name'], 'birth' => fmt_date($r['birth_date']),
            'age' => fmt_age($r['birth_date'], $r['exit_date']), 'origin' => label('origin', $r['origin']),
            'last_weight' => $r['last_weight'] !== null ? fmt_num($r['last_weight']) . ' kg' : '',
            'status' => label('status', $r['status']), 'exit' => fmt_date($r['exit_date']),
        ], $rows));
        $pdf->send('animaux_' . date('Ymd_His') . '.pdf', !isset($_GET['download']));
    }

    /** Autocomplétion par n° de boucle (JSON). */
    public function search(): never
    {
        $this->authorize('animals.view');
        $q = trim((string) ($_GET['q'] ?? ''));
        $where = ['(tag LIKE :q1 OR name LIKE :q2)'];
        $p = ['q1' => $q . '%', 'q2' => $q . '%'];
        if (in_array($_GET['sex'] ?? '', ['M', 'F'], true)) {
            $where[] = 'sex = :sex';
            $p['sex'] = $_GET['sex'];
        }
        if (empty($_GET['all'])) {
            $where[] = "status = 'actif'";
        }
        $rows = Database::all('SELECT id, tag, name, species, sex FROM animals WHERE ' . implode(' AND ', $where) . ' ORDER BY tag LIMIT 20', $p);
        json_response(array_map(static fn($r) => [
            'id' => (int) $r['id'], 'tag' => $r['tag'],
            'label' => $r['tag'] . ($r['name'] ? ' — ' . $r['name'] : '') . ' (' . label('species', $r['species']) . ', ' . $r['sex'] . ')',
        ], $rows));
    }

    // =================================================================
    //  Fiche animal
    // =================================================================
    private function load(int $id): array
    {
        $a = Database::one(
            'SELECT a.*, c.label AS category_label, c.code AS category_code, b.name AS breed_name, l.name AS lot_name,
                    m.tag AS mother_tag, m.name AS mother_name, f.tag AS father_tag, f.name AS father_name,
                    uc.full_name AS created_by_name, uu.full_name AS updated_by_name
             FROM animals a JOIN categories c ON c.id = a.category_id
             LEFT JOIN breeds b ON b.id = a.breed_id LEFT JOIN lots l ON l.id = a.lot_id
             LEFT JOIN animals m ON m.id = a.mother_id LEFT JOIN animals f ON f.id = a.father_id
             LEFT JOIN users uc ON uc.id = a.created_by LEFT JOIN users uu ON uu.id = a.updated_by
             WHERE a.id = :id',
            ['id' => $id]
        );
        if (!$a) {
            abort(404, 'Animal introuvable.');
        }
        return $a;
    }

    /** Données complètes de la fiche (écran + PDF). */
    private function fiche(int $id): array
    {
        $a = $this->load($id);
        $tracking = [];
        foreach (Tracking::all() as $type => $cfg) {
            $cfg = Tracking::get($type);
            if (!Auth::can("$type.view")) {
                continue;
            }
            [$select, $from] = Tracking::sql($cfg);
            $col = in_array($type, ['matings'], true) ? '(t.animal_id = :id1 OR t.male_id = :id2)'
                : ($type === 'births' ? '(t.animal_id = :id1 OR t.father_id = :id2)' : 't.animal_id = :id1');
            $tracking[$type] = [
                'cfg'  => $cfg,
                'rows' => Database::all("SELECT $select $from WHERE $col ORDER BY t.event_date DESC, t.id DESC LIMIT 200", ['id1' => $id, 'id2' => $id]),
            ];
        }
        $offspring = Database::all(
            'SELECT a.id, a.tag, a.name, a.sex, a.birth_date, a.status, c.label AS category_label FROM animals a
             JOIN categories c ON c.id = a.category_id WHERE a.mother_id = :m OR a.father_id = :f ORDER BY a.birth_date DESC LIMIT 200',
            ['m' => $id, 'f' => $id]
        );
        $history = Database::all(
            'SELECT h.*, u.full_name FROM animal_history h LEFT JOIN users u ON u.id = h.user_id
             WHERE h.animal_id = :id ORDER BY h.event_date DESC, h.id DESC LIMIT 300',
            ['id' => $id]
        );
        $indices = Auth::can('indices.view') ? Database::all(
            'SELECT r.*, d.name, d.unit, d.decimals FROM index_results r JOIN index_definitions d ON d.id = r.index_id
             WHERE r.animal_id = :id ORDER BY r.year DESC, d.sort_order',
            ['id' => $id]
        ) : [];
        $weights = Database::all('SELECT event_date, weight FROM weighings WHERE animal_id = :id ORDER BY event_date, id', ['id' => $id]);
        return compact('a', 'tracking', 'offspring', 'history', 'indices', 'weights');
    }

    public function show(): never
    {
        $this->authorize('animals.view');
        $this->render('animals/show', $this->fiche(query_int('id')));
    }

    public function pdf(): never
    {
        $this->authorize('animals.export');
        $data = $this->fiche(query_int('id'));
        $a = $data['a'];
        Audit::log('export', 'animal', (int) $a['id'], 'Fiche PDF de l\'animal ' . $a['tag']);

        $pdf = new PdfReport('Fiche animal : ' . $a['tag'] . ($a['name'] ? ' — ' . $a['name'] : ''),
            $a['category_label'] . ' · ' . label('species', $a['species']) . ' · ' . label('status', $a['status']));
        $pdf->addPage();
        $pdf->sectionTitle('Identification');
        $pdf->keyValues([
            'N° boucle' => $a['tag'], 'Nom' => $a['name'] ?: '—', 'Espèce' => label('species', $a['species']),
            'Catégorie' => $a['category_label'], 'Sexe' => label('sex', $a['sex']), 'Race' => $a['breed_name'] ?: '—',
            'Robe' => $a['color'] ?: '—', 'Lot' => $a['lot_name'] ?: '—',
            'Naissance' => fmt_date($a['birth_date']) . ((int) $a['birth_date_estimated'] ? ' (estimée)' : ''),
            'Âge' => fmt_age($a['birth_date'], $a['exit_date']),
            'Poids de naissance' => $a['birth_weight'] !== null ? fmt_num($a['birth_weight']) . ' kg' : '—',
            'Type de naissance' => $a['birth_type'] ? label('birth_type', $a['birth_type']) : '—',
            'Mère' => $a['mother_tag'] ?: '—', 'Père' => $a['father_tag'] ?: '—',
            'Origine' => label('origin', $a['origin']), 'Entrée' => fmt_date($a['entry_date']),
            'Prix d\'achat' => $a['purchase_price'] !== null ? fmt_money($a['purchase_price']) : '—',
            'Statut' => label('status', $a['status']),
            'Sortie' => $a['exit_date'] ? fmt_date($a['exit_date']) . ($a['exit_reason'] ? ' — ' . $a['exit_reason'] : '') : '—',
            'Prix de vente' => $a['sale_price'] !== null ? fmt_money($a['sale_price']) : '—',
        ]);
        if ($a['notes']) {
            $pdf->setFont(false, 8.5);
            $pdf->paragraph('Remarques : ' . $a['notes'], 4.5);
        }
        foreach ($data['tracking'] as $type => $t) {
            if (!$t['rows']) {
                continue;
            }
            $cfg = $t['cfg'];
            $pdf->sectionTitle($cfg['title'] . ' (' . count($t['rows']) . ')');
            $cols = [];
            foreach ($cfg['list'] as $f) {
                $cols[] = ['label' => $cfg['fields'][$f]['label'], 'key' => $f, 'w' => $f === 'notes' ? 2 : 1,
                    'align' => $cfg['fields'][$f]['type'] === 'number' ? 'R' : 'L'];
            }
            $pdf->table($cols, array_map(static function ($r) use ($cfg) {
                $o = [];
                foreach ($cfg['list'] as $f) {
                    $o[$f] = Tracking::format($cfg, $f, $r, false);
                }
                return $o;
            }, $t['rows']), 7.5);
        }
        if ($data['offspring']) {
            $pdf->sectionTitle('Descendance (' . count($data['offspring']) . ')');
            $pdf->table([
                ['label' => 'N° boucle', 'key' => 'tag'], ['label' => 'Nom', 'key' => 'name'], ['label' => 'Catégorie', 'key' => 'category_label'],
                ['label' => 'Naissance', 'key' => 'birth'], ['label' => 'Statut', 'key' => 'status'],
            ], array_map(static fn($r) => ['tag' => $r['tag'], 'name' => $r['name'], 'category_label' => $r['category_label'],
                'birth' => fmt_date($r['birth_date']), 'status' => label('status', $r['status'])], $data['offspring']), 7.5);
        }
        if ($data['indices']) {
            $pdf->sectionTitle('Indices calculés');
            $pdf->table([
                ['label' => 'Année', 'key' => 'year', 'w' => 0.6], ['label' => 'Indice', 'key' => 'name', 'w' => 1.8],
                ['label' => 'Valeur', 'key' => 'value', 'w' => 0.9, 'align' => 'R'], ['label' => 'Niveau', 'key' => 'level', 'w' => 0.7],
                ['label' => 'Interprétation', 'key' => 'interpretation', 'w' => 4],
            ], array_map(static fn($r) => ['year' => $r['year'] ?: 'Vie', 'name' => $r['name'],
                'value' => $r['value'] !== null ? fmt_num($r['value'], (int) $r['decimals']) . ' ' . $r['unit'] : 'N/D',
                'level' => label('level', $r['level']), 'interpretation' => $r['interpretation']], $data['indices']), 7.5);
        }
        if ($data['history']) {
            $pdf->sectionTitle('Historique');
            $pdf->table([
                ['label' => 'Date', 'key' => 'date', 'w' => 0.8], ['label' => 'Type', 'key' => 'type', 'w' => 1],
                ['label' => 'Description', 'key' => 'summary', 'w' => 4.5], ['label' => 'Par', 'key' => 'user', 'w' => 1.2],
            ], array_map(static fn($r) => ['date' => fmt_date($r['event_date']), 'type' => label('history_type', $r['event_type']),
                'summary' => $r['summary'], 'user' => $r['full_name']], array_slice($data['history'], 0, 100)), 7.5);
        }
        $pdf->send('fiche_' . $a['tag'] . '.pdf', !isset($_GET['download']));
    }

    // =================================================================
    //  Création / modification / suppression
    // =================================================================
    public function create(): never
    {
        $this->authorize('animals.create');
        $animal = ['status' => 'actif', 'origin' => 'naissance', 'entry_date' => date('Y-m-d')];
        $this->renderForm($animal, false);
    }

    public function edit(): never
    {
        $this->authorize('animals.edit');
        $this->renderForm($this->load(query_int('id')), true);
    }

    private function renderForm(array $animal, bool $isEdit): never
    {
        $this->render('animals/form', [
            'animal' => $animal, 'isEdit' => $isEdit, 'errors' => form_errors(),
            'categories' => Ref::categories(), 'breeds' => Ref::breeds(), 'lots' => Ref::lots(),
        ]);
    }

    /** Valide la saisie ; retourne [données, erreurs]. */
    private function validate(?array $existing): array
    {
        $v = Validator::make($_POST, [
            'tag' => 'required|maxlen:30|regex:/^[\w\-\/. ]+$/u', 'name' => 'maxlen:80', 'category_id' => 'required|int',
            'breed_id' => 'int', 'lot_id' => 'int', 'birth_date' => 'required|date', 'birth_date_estimated' => 'bool',
            'birth_weight' => 'numeric|min:0|max:20', 'birth_type' => 'in:simple,double,triple,quadruple+',
            'origin' => 'required|in:naissance,achat,don,autre', 'entry_date' => 'required|date',
            'purchase_price' => 'numeric|min:0', 'color' => 'maxlen:60', 'status' => 'required|in:actif,vendu,mort,reforme,perdu',
            'exit_date' => 'date', 'exit_reason' => 'maxlen:160', 'sale_price' => 'numeric|min:0', 'notes' => 'maxlen:5000',
            'mother_tag' => 'maxlen:30', 'father_tag' => 'maxlen:30',
        ], self::FIELD_LABELS + ['mother_tag' => 'Mère', 'father_tag' => 'Père']);
        $d = $v->data();

        $cat = Ref::categories()[(int) $d['category_id']] ?? null;
        if (!$cat) {
            $v->addError('category_id', 'Catégorie invalide.');
        } else {
            $d['species'] = $cat['species'];
            $d['sex'] = $cat['sex'];
        }
        $tagOwner = Database::value('SELECT id FROM animals WHERE tag = :t', ['t' => (string) $d['tag']]);
        if ($tagOwner && (!$existing || (int) $tagOwner !== (int) $existing['id'])) {
            $v->addError('tag', 'Ce numéro de boucle existe déjà.');
        }
        if ($d['breed_id'] && $cat && (Ref::breeds()[(int) $d['breed_id']]['species'] ?? null) !== $cat['species']) {
            $v->addError('breed_id', 'La race ne correspond pas à l\'espèce.');
        }
        if ($d['birth_date'] && $d['birth_date'] > date('Y-m-d')) {
            $v->addError('birth_date', 'La date de naissance ne peut pas être dans le futur.');
        }
        if ($d['entry_date'] && $d['birth_date'] && $d['entry_date'] < $d['birth_date']) {
            $v->addError('entry_date', 'La date d\'entrée doit être postérieure à la naissance.');
        }
        if ($d['status'] !== 'actif' && !$d['exit_date']) {
            $v->addError('exit_date', 'La date de sortie est obligatoire pour un animal sorti.');
        }
        if ($d['status'] === 'actif') {
            $d['exit_date'] = $d['exit_reason'] = $d['sale_price'] = null;
        } elseif ($d['exit_date'] && $d['entry_date'] && $d['exit_date'] < $d['entry_date']) {
            $v->addError('exit_date', 'La date de sortie doit être postérieure à l\'entrée.');
        }
        foreach (['mother_tag' => ['mother_id', 'F'], 'father_tag' => ['father_id', 'M']] as $k => [$col, $sex]) {
            $d[$col] = null;
            if ($d[$k]) {
                $parent = Database::one('SELECT id, sex, species, birth_date FROM animals WHERE tag = :t', ['t' => $d[$k]]);
                if (!$parent) {
                    $v->addError($k, 'Parent introuvable : ' . $d[$k]);
                } elseif ($parent['sex'] !== $sex) {
                    $v->addError($k, $sex === 'F' ? 'La mère doit être une femelle.' : 'Le père doit être un mâle.');
                } elseif ($cat && $parent['species'] !== $cat['species']) {
                    $v->addError($k, 'Le parent doit être de la même espèce.');
                } elseif ($existing && (int) $parent['id'] === (int) $existing['id']) {
                    $v->addError($k, 'Un animal ne peut pas être son propre parent.');
                } elseif ($d['birth_date'] && $parent['birth_date'] >= $d['birth_date']) {
                    $v->addError($k, 'Le parent doit être né avant l\'animal.');
                } else {
                    $d[$col] = (int) $parent['id'];
                }
            }
            unset($d[$k]);
        }
        foreach (['breed_id', 'lot_id', 'category_id'] as $k) {
            $d[$k] = $d[$k] ? (int) $d[$k] : null;
        }
        foreach (['name', 'color', 'exit_reason', 'notes', 'birth_type'] as $k) {
            $d[$k] = ($d[$k] ?? '') === '' ? null : $d[$k];
        }
        return [$d, $v->errors()];
    }

    public function store(): never
    {
        $this->authorize('animals.create');
        [$d, $errors] = $this->validate(null);
        if ($errors) {
            with_input($_POST, $errors);
            redirect(url('animals/create'));
        }
        $d['created_by'] = Auth::id();
        $id = Database::transaction(static function () use ($d) {
            $id = Database::insert('animals', $d);
            History::add($id, $d['entry_date'], 'creation',
                $d['origin'] === 'naissance' ? 'Naissance sur l\'exploitation — fiche créée' : 'Entrée dans le troupeau (' . label('origin', $d['origin']) . ')');
            if ($d['status'] !== 'actif') {
                History::add($id, $d['exit_date'], 'sortie', 'Sortie : ' . label('status', $d['status']) . ($d['exit_reason'] ? ' — ' . $d['exit_reason'] : ''));
            }
            return $id;
        });
        Audit::log('create', 'animal', $id, 'Création de l\'animal ' . $d['tag'], null, $d);
        Cache::bump('animals');
        flash('success', 'Animal ' . $d['tag'] . ' enregistré.');
        redirect(isset($_POST['save_new']) ? url('animals/create') : url('animals/show', ['id' => $id]));
    }

    public function update(): never
    {
        $this->authorize('animals.edit');
        $id = (int) ($_POST['id'] ?? 0);
        $old = Database::find('animals', $id) ?? abort(404, 'Animal introuvable.');
        [$d, $errors] = $this->validate($old);
        if ($errors) {
            with_input($_POST, $errors);
            redirect(url('animals/edit', ['id' => $id]));
        }
        $d['updated_by'] = Auth::id();
        [$before, $after] = Audit::diff($old, $d);
        unset($before['updated_by'], $after['updated_by']);

        Database::transaction(function () use ($id, $d, $old, $before, $after) {
            Database::update('animals', $d, $id);
            if ($after) {
                $parts = [];
                foreach ($after as $k => $nv) {
                    $parts[] = (self::FIELD_LABELS[$k] ?? $k) . ' : ' . $this->display($k, $before[$k]) . ' → ' . $this->display($k, $nv);
                }
                History::add($id, date('Y-m-d'), 'modification', 'Modification — ' . implode(' ; ', $parts), 'animals', $id,
                    ['avant' => $before, 'apres' => $after]);
            }
            if ($old['status'] === 'actif' && $d['status'] !== 'actif') {
                History::add($id, $d['exit_date'], 'sortie', 'Sortie : ' . label('status', $d['status']) . ($d['exit_reason'] ? ' — ' . $d['exit_reason'] : ''));
            }
        });
        Audit::log('update', 'animal', $id, 'Modification de l\'animal ' . $d['tag'], $old, $d);
        Cache::bump('animals');
        flash('success', 'Fiche mise à jour.');
        redirect(url('animals/show', ['id' => $id]));
    }

    private function display(string $field, mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        return match ($field) {
            'category_id' => Ref::categoryLabel((int) $v),
            'breed_id'    => Ref::breedName((int) $v),
            'lot_id'      => Ref::lotName((int) $v),
            'mother_id', 'father_id' => (string) Database::value('SELECT tag FROM animals WHERE id = :id', ['id' => (int) $v]),
            'status'      => label('status', (string) $v),
            'origin'      => label('origin', (string) $v),
            'birth_date', 'entry_date', 'exit_date' => fmt_date((string) $v),
            default       => (string) $v,
        };
    }

    public function delete(): never
    {
        $this->authorize('animals.delete');
        $id = (int) ($_POST['id'] ?? 0);
        $old = Database::find('animals', $id) ?? abort(404, 'Animal introuvable.');
        Database::delete('animals', $id); // suivis et historique supprimés en cascade (InnoDB)
        Audit::log('delete', 'animal', $id, 'Suppression de l\'animal ' . $old['tag'], $old, null);
        Cache::bump('animals', 'tracking', 'indices');
        flash('success', 'Animal ' . $old['tag'] . ' supprimé.');
        redirect(url('animals'));
    }

    /** Reclasse les jeunes ayant dépassé l'âge adulte (chevreau → bouc, agnelle → brebis...). */
    public function reclassify(): never
    {
        $this->authorize('animals.edit');
        $months = max(1, (int) setting('age_adulte_mois', 12));
        $cats = Database::pairs('SELECT code, id FROM categories');
        $map = ['chevreau' => 'bouc', 'chevrette' => 'chevre', 'agneau' => 'belier', 'agnelle' => 'brebis'];
        $total = 0;
        Database::transaction(static function () use ($map, $cats, $months, &$total) {
            foreach ($map as $young => $adult) {
                if (!isset($cats[$young], $cats[$adult])) {
                    continue;
                }
                $cond = 'category_id = :y AND status = \'actif\' AND birth_date <= DATE_SUB(CURDATE(), INTERVAL ' . $months . ' MONTH)';
                Database::query("INSERT INTO animal_history (animal_id, event_date, event_type, summary, user_id)
                    SELECT id, CURDATE(), 'modification', :s, :u FROM animals WHERE $cond",
                    ['s' => 'Reclassement automatique : ' . Ref::categoryLabel((int) $cats[$young]) . ' → ' . Ref::categoryLabel((int) $cats[$adult]),
                     'u' => Auth::id(), 'y' => (int) $cats[$young]]);
                $total += Database::query("UPDATE animals SET category_id = :a WHERE $cond", ['a' => (int) $cats[$adult], 'y' => (int) $cats[$young]])->rowCount();
            }
        });
        Audit::log('update', 'animal', null, "Reclassement jeunes → adultes : $total animal(aux)");
        Cache::bump('animals');
        flash('success', "$total animal(aux) reclassé(s) en catégorie adulte (âge ≥ $months mois).");
        redirect(url('animals'));
    }

    /** Calcule les indices de l'animal (année choisie + vie entière). */
    public function compute(): never
    {
        $this->authorize('indices.compute');
        $id = (int) ($_POST['id'] ?? 0);
        $a = $this->load($id);
        $year = (int) ($_POST['year'] ?? date('Y'));
        $calc = new IndexCalculator();
        $n = $calc->computeAnimals($year, $id)['saved'] + $calc->computeAnimals(0, $id)['saved'];
        Audit::log('compute', 'animal', $id, "Calcul des indices de {$a['tag']} ($year + vie entière) : $n résultat(s)");
        History::add($id, date('Y-m-d'), 'indice', "Calcul des indices ($year et vie entière)");
        flash('success', "$n indice(s) calculé(s) et enregistré(s).");
        redirect(url('animals/show', ['id' => $id, 'tab' => 'indices']));
    }
}
