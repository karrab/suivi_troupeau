<?php
/** Barre d'actions d'une liste : imprimer + exporter PDF (avec filtres courants). $exportRoute, $exportPerm */
?>
<div class="btn-group d-print-none">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
    <?php if (can($exportPerm)): ?>
        <a class="btn btn-outline-danger btn-sm" target="_blank" href="<?= e(url($exportRoute, query_with($exportParams ?? [], ['r', 'cursor', 'dir_page', 'per_page']))) ?>">
            <i class="bi bi-file-earmark-pdf me-1"></i>Exporter PDF
        </a>
    <?php endif; ?>
</div>
