<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Validator;

/**
 * Référentiels : races et lots.
 */
final class ReferenceController extends Controller
{
    private const TYPES = [
        'breeds' => [
            'title' => 'Races', 'singular' => 'race', 'table' => 'breeds', 'fk' => 'breed_id',
            'fields' => ['name' => ['Nom', 'required|maxlen:80'], 'species' => ['Espèce', 'required|in:caprin,ovin'], 'description' => ['Description', 'maxlen:255']],
        ],
        'lots' => [
            'title' => 'Lots / parcs', 'singular' => 'lot', 'table' => 'lots', 'fk' => 'lot_id',
            'fields' => ['name' => ['Nom', 'required|maxlen:80'], 'location' => ['Emplacement', 'maxlen:120'], 'description' => ['Description', 'maxlen:255']],
        ],
    ];

    private function type(): array
    {
        $t = (string) ($_GET['type'] ?? $_POST['type'] ?? 'breeds');
        return [$t, self::TYPES[$t] ?? abort(404)];
    }

    public function index(): never
    {
        $this->authorize('references.view');
        [$t, $cfg] = $this->type();
        $rows = Database::all("SELECT x.*, (SELECT COUNT(*) FROM animals a WHERE a.{$cfg['fk']} = x.id AND a.status = 'actif') AS nb
                               FROM `{$cfg['table']}` x ORDER BY x.name");
        $edit = query_int('edit') ? Database::find($cfg['table'], query_int('edit')) : null;
        $this->render('references/index', ['t' => $t, 'cfg' => $cfg, 'rows' => $rows, 'edit' => $edit, 'types' => self::TYPES, 'errors' => form_errors()]);
    }

    public function save(): never
    {
        $this->authorize('references.manage');
        [$t, $cfg] = $this->type();
        $id = (int) ($_POST['id'] ?? 0);
        $rules = $labels = [];
        foreach ($cfg['fields'] as $f => [$label, $rule]) {
            $rules[$f] = $rule;
            $labels[$f] = $label;
        }
        $v = Validator::make($_POST, $rules, $labels);
        $d = $v->data();
        $dupSql = "SELECT id FROM `{$cfg['table']}` WHERE name = :n" . ($t === 'breeds' ? ' AND species = :s' : '');
        $dup = Database::value($dupSql, ['n' => (string) $d['name'], 's' => (string) ($d['species'] ?? '')]);
        if ($dup && (int) $dup !== $id) {
            $v->addError('name', 'Ce nom existe déjà.');
        }
        if ($v->fails()) {
            with_input($_POST, $v->errors());
            redirect(url('references', ['type' => $t, 'edit' => $id ?: null]));
        }
        if ($id) {
            $old = Database::find($cfg['table'], $id) ?? abort(404);
            Database::update($cfg['table'], $d, $id);
            Audit::log('update', $cfg['table'], $id, ucfirst($cfg['singular']) . ' ' . $d['name'], $old, $d);
        } else {
            $id = Database::insert($cfg['table'], $d);
            Audit::log('create', $cfg['table'], $id, ucfirst($cfg['singular']) . ' ' . $d['name'], null, $d);
        }
        Cache::bump('refs');
        flash('success', ucfirst($cfg['singular']) . ' enregistré(e).');
        redirect(url('references', ['type' => $t]));
    }

    public function delete(): never
    {
        $this->authorize('references.manage');
        [$t, $cfg] = $this->type();
        $id = (int) ($_POST['id'] ?? 0);
        $old = Database::find($cfg['table'], $id) ?? abort(404);
        Database::delete($cfg['table'], $id); // FK ON DELETE SET NULL sur animals
        Audit::log('delete', $cfg['table'], $id, ucfirst($cfg['singular']) . ' ' . $old['name'], $old, null);
        Cache::bump('refs', 'animals');
        flash('success', ucfirst($cfg['singular']) . ' supprimé(e).');
        redirect(url('references', ['type' => $t]));
    }
}
