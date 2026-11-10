<?php
/** Pagination keyset : boutons Précédent / Suivant + choix du nombre de lignes. */
/** @var array $page */
$total = $total ?? null;
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 d-print-none">
    <div class="small text-muted">
        <?= count($page['rows']) ?> ligne(s) affichée(s)<?= $total !== null ? ' sur ' . fmt_num($total, 0) : '' ?>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-1" action="<?= e(url()) ?>">
            <?php foreach (query_with([], ['per_page', 'cursor', 'dir_page']) as $k => $v): if (is_array($v)) continue; ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
            <?php endforeach; ?>
            <select name="per_page" class="form-select form-select-sm" data-autosubmit aria-label="Lignes par page">
                <?php foreach ([10, 25, 50, 100, 200] as $n): ?>
                    <option value="<?= $n ?>" <?= $n === $page['per_page'] ? 'selected' : '' ?>><?= $n ?> / page</option>
                <?php endforeach; ?>
            </select>
        </form>
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page['is_first'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(url_with([], ['cursor', 'dir_page'])) ?>" title="Première page"><i class="bi bi-chevron-double-left"></i></a>
                </li>
                <li class="page-item <?= $page['prev'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= $page['prev'] ? e(url_with(['cursor' => $page['prev'], 'dir_page' => 'before'])) : '#' ?>"><i class="bi bi-chevron-left"></i> Précédent</a>
                </li>
                <li class="page-item <?= $page['next'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= $page['next'] ? e(url_with(['cursor' => $page['next'], 'dir_page' => 'after'])) : '#' ?>">Suivant <i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
</div>
