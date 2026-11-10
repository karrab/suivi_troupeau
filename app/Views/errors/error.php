<div class="container py-5">
    <div class="text-center">
        <div class="display-3 fw-bold text-forest"><?= (int) $code ?></div>
        <p class="lead"><?= e($message ?: 'Une erreur est survenue.') ?></p>
        <a href="<?= e(url('dashboard')) ?>" class="btn btn-forest"><i class="bi bi-house me-1"></i>Retour à l'accueil</a>
    </div>
</div>
