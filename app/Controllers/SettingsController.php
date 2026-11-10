<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\FormulaEngine;
use App\Core\Settings;
use App\Core\Validator;
use App\Services\IndexCalculator;
use App\Services\PermissionSync;

final class SettingsController extends Controller
{
    // ------------------------------------------------------- généraux
    public function general(): never
    {
        $this->authorize('settings.manage');
        $rows = Database::all('SELECT * FROM settings ORDER BY sgroup, sort_order');
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r['sgroup']][] = $r;
        }
        $this->render('settings/general', ['groups' => $groups, 'errors' => form_errors()]);
    }

    public function save(): never
    {
        $this->authorize('settings.manage');
        $rows = Database::all('SELECT * FROM settings');
        $old = [];
        $new = [];
        $errors = [];
        foreach ($rows as $r) {
            $k = $r['skey'];
            if (!array_key_exists($k, $_POST['s'] ?? [])) {
                continue;
            }
            $v = trim((string) $_POST['s'][$k]);
            if (in_array($r['stype'], ['int', 'number'], true) && !is_numeric($v)) {
                $errors[$k] = "« {$r['label']} » doit être numérique.";
                continue;
            }
            if ($r['stype'] === 'int') {
                $v = (string) max(0, (int) $v);
            }
            if ((string) $r['svalue'] !== $v) {
                $old[$k] = $r['svalue'];
                $new[$k] = $v;
                Database::query('UPDATE settings SET svalue = :v WHERE skey = :k', ['v' => mb_substr($v, 0, 1000), 'k' => $k]);
            }
        }
        Cache::bump('settings');
        if ($new) {
            Audit::log('settings', 'settings', null, 'Modification des paramètres : ' . implode(', ', array_keys($new)), $old, $new);
        }
        if ($errors) {
            with_input([], $errors);
            flash('warning', 'Certains paramètres n\'ont pas été enregistrés.');
        } else {
            flash('success', 'Paramètres enregistrés.');
        }
        redirect(url('settings'));
    }

    // -------------------------------------------- définitions d'indices
    public function indices(): never
    {
        $this->authorize('indices.view');
        $defs = Database::all('SELECT d.*, (SELECT COUNT(*) FROM index_results r WHERE r.index_id = d.id) AS nb_results
                               FROM index_definitions d ORDER BY d.scope DESC, d.sort_order, d.id');
        $this->render('settings/indices', ['defs' => $defs]);
    }

    public function indexForm(): never
    {
        $this->authorize('indices.formulas');
        $id = query_int('id');
        $def = $id ? (Database::find('index_definitions', $id) ?? abort(404)) : [
            'scope' => $_GET['scope'] ?? 'troupeau', 'better' => 'haut', 'decimals' => 2, 'is_active' => 1, 'sort_order' => 100,
        ];
        $history = $id ? Database::all(
            'SELECT h.*, u.full_name FROM index_formula_history h LEFT JOIN users u ON u.id = h.user_id
             WHERE h.index_id = :id ORDER BY h.changed_at DESC, h.id DESC LIMIT 50', ['id' => $id]) : [];
        $this->render('settings/index_form', [
            'def' => $def, 'history' => $history, 'errors' => form_errors(),
            'animalVars' => IndexCalculator::ANIMAL_VARS, 'herdVars' => IndexCalculator::HERD_VARS,
        ]);
    }

    private function validateDef(?array $existing): array
    {
        $v = Validator::make($_POST, [
            'code' => 'required|maxlen:40|regex:/^[a-z0-9_]+$/', 'name' => 'required|maxlen:120', 'description' => 'maxlen:500',
            'scope' => 'required|in:animal,troupeau', 'formula' => 'required|maxlen:1000', 'unit' => 'maxlen:20',
            'decimals' => 'required|int|min:0|max:6', 'better' => 'required|in:haut,bas',
            'threshold_low' => 'numeric', 'threshold_high' => 'numeric',
            'text_good' => 'maxlen:255', 'text_medium' => 'maxlen:255', 'text_bad' => 'maxlen:255',
            'is_active' => 'bool', 'sort_order' => 'int',
        ], [
            'code' => 'Code', 'name' => 'Nom', 'formula' => 'Formule', 'scope' => 'Portée', 'decimals' => 'Décimales',
            'threshold_low' => 'Seuil bas', 'threshold_high' => 'Seuil haut',
        ]);
        $d = $v->data();
        if (!$v->fails()) {
            $err = (new FormulaEngine())->validate((string) $d['formula'], array_keys(IndexCalculator::variablesFor($d['scope'])));
            if ($err) {
                $v->addError('formula', 'Formule invalide : ' . $err);
            }
            if ($d['threshold_low'] !== null && $d['threshold_high'] !== null && (float) $d['threshold_low'] > (float) $d['threshold_high']) {
                $v->addError('threshold_high', 'Le seuil haut doit être supérieur ou égal au seuil bas.');
            }
            $dup = Database::value('SELECT id FROM index_definitions WHERE code = :c', ['c' => $d['code']]);
            if ($dup && (!$existing || (int) $dup !== (int) $existing['id'])) {
                $v->addError('code', 'Ce code existe déjà.');
            }
        }
        foreach (['description', 'unit', 'text_good', 'text_medium', 'text_bad'] as $k) {
            $d[$k] = ($d[$k] ?? '') === '' ? null : $d[$k];
        }
        $d['sort_order'] = (int) ($d['sort_order'] ?? 0);
        return [$d, $v->errors()];
    }

    public function indexSave(): never
    {
        $this->authorize('indices.formulas');
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id ? (Database::find('index_definitions', $id) ?? abort(404)) : null;
        [$d, $errors] = $this->validateDef($existing);
        if ($errors) {
            with_input($_POST, $errors);
            redirect(url('settings/index-form', $id ? ['id' => $id] : ['scope' => $_POST['scope'] ?? 'troupeau']));
        }
        $d['updated_by'] = Auth::id();
        Database::transaction(static function () use (&$id, $d, $existing) {
            if ($existing) {
                Database::update('index_definitions', $d, $id);
                if ($existing['formula'] !== $d['formula']) {
                    Database::insert('index_formula_history', ['index_id' => $id, 'old_formula' => $existing['formula'],
                        'new_formula' => $d['formula'], 'user_id' => Auth::id()]);
                }
            } else {
                $id = Database::insert('index_definitions', $d);
                Database::insert('index_formula_history', ['index_id' => $id, 'old_formula' => null, 'new_formula' => $d['formula'], 'user_id' => Auth::id()]);
            }
        });
        Audit::log($existing ? 'update' : 'create', 'index_definition', $id, 'Indice ' . $d['code'] . ($existing && $existing['formula'] !== $d['formula'] ? ' (formule modifiée)' : ''), $existing, $d);
        Cache::bump('indices');
        flash('success', 'Indice « ' . $d['name'] . ' » enregistré. Relancez le calcul pour mettre à jour les résultats.');
        redirect(url('settings/indices'));
    }

    public function indexDelete(): never
    {
        $this->authorize('indices.formulas');
        $id = (int) ($_POST['id'] ?? 0);
        $old = Database::find('index_definitions', $id) ?? abort(404);
        Database::delete('index_definitions', $id);
        Audit::log('delete', 'index_definition', $id, 'Suppression de l\'indice ' . $old['code'], $old, null);
        Cache::bump('indices');
        flash('success', 'Indice supprimé (ainsi que ses résultats).');
        redirect(url('settings/indices'));
    }

    /** Test AJAX d'une formule sur les données réelles. */
    public function indexTest(): never
    {
        $this->authorize('indices.formulas');
        $scope = ($_POST['scope'] ?? '') === 'animal' ? 'animal' : 'troupeau';
        $formula = (string) ($_POST['formula'] ?? '');
        $err = (new FormulaEngine())->validate($formula, array_keys(IndexCalculator::variablesFor($scope)));
        if ($err) {
            json_response(['ok' => false, 'error' => $err]);
        }
        $def = [
            'scope' => $scope, 'formula' => $formula, 'decimals' => (int) ($_POST['decimals'] ?? 2), 'unit' => (string) ($_POST['unit'] ?? ''),
            'better' => ($_POST['better'] ?? 'haut') === 'bas' ? 'bas' : 'haut',
            'threshold_low' => is_numeric($_POST['threshold_low'] ?? '') ? $_POST['threshold_low'] : null,
            'threshold_high' => is_numeric($_POST['threshold_high'] ?? '') ? $_POST['threshold_high'] : null,
            'text_good' => $_POST['text_good'] ?? '', 'text_medium' => $_POST['text_medium'] ?? '', 'text_bad' => $_POST['text_bad'] ?? '',
        ];
        $year = (int) ($_POST['year'] ?? date('Y'));
        $out = array_map(static fn($r) => [
            'label' => $r['label'],
            'value' => $r['value'] === null ? 'N/D' : number_format($r['value'], $def['decimals'], ',', ' ') . ' ' . $def['unit'],
            'level' => $r['interp'][0], 'text' => $r['interp'][1],
        ], (new IndexCalculator())->preview($def, $year));
        json_response(['ok' => true, 'year' => $year, 'results' => $out]);
    }

    // ------------------------------------------------------- système
    public function system(): never
    {
        $this->authorize('settings.system');
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $tables = Database::all(
            'SELECT table_name AS name, engine, table_rows AS rows_est, ROUND((data_length + index_length) / 1024) AS size_kb,
                    ROUND(index_length / 1024) AS index_kb
             FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name'
        );
        $this->render('settings/system', [
            'opcache' => $opcache, 'apcu' => Cache::stats(), 'tables' => $tables,
            'dbVersion' => Database::value('SELECT VERSION()'),
        ]);
    }

    public function cacheClear(): never
    {
        $this->authorize('settings.system');
        Cache::clear();
        if (!empty($_POST['opcache']) && function_exists('opcache_reset')) {
            opcache_reset();
        }
        Audit::log('cache', 'system', null, 'Vidage du cache' . (!empty($_POST['opcache']) ? ' (APCu + OPcache)' : ' (APCu)'));
        flash('success', 'Cache vidé.');
        redirect(url('settings/system'));
    }

    public function syncPermissions(): never
    {
        $this->authorize('settings.system');
        $n = PermissionSync::sync();
        Audit::log('settings', 'permission', null, "Synchronisation des permissions ($n nouvelle(s))");
        flash('success', "Permissions synchronisées ($n nouvelle(s)).");
        redirect(url('settings/system'));
    }
}
