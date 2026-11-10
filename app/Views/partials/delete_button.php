<?php
/**
 * Bouton supprimer (formulaire POST + CSRF + confirmation modale).
 * Variables : $action (url), $id, $message, $extra (champs cachés), $label (bool), $class
 */
$extra = $extra ?? [];
?>
<form method="post" action="<?= e($action) ?>" class="d-inline" data-confirm="<?= e($message ?? 'Confirmer la suppression ?') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php foreach ($extra as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
    <button type="submit" class="<?= e($class ?? 'btn btn-sm btn-outline-danger') ?>" title="Supprimer">
        <i class="bi bi-trash"></i><?= !empty($label) ? ' Supprimer' : '' ?>
    </button>
</form>
