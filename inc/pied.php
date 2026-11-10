</main>
</div></div>

<!-- Confirmation de suppression -->
<div class="modal fade" id="fenetreConfirmation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Confirmation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="messageConfirmation">Confirmer la suppression ?</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="button" class="btn btn-danger" id="boutonConfirmer"><i class="bi bi-trash me-1"></i>Confirmer</button></div>
    </div></div>
</div>
<datalist id="liste-boucles"></datalist>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>" id="script-app"></script>
</body>
</html>
