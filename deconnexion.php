<?php
/** Déconnexion (POST uniquement, protégée par le jeton CSRF). */
require __DIR__ . '/inc/init.php';
exiger_post();
deconnecter();
rediriger('connexion.php');
