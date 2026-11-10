<?php
/**
 * Table de routage : r => [Contrôleur, méthode, options]
 * options : public (sans connexion), post (POST obligatoire)
 */
use App\Controllers\AnimalController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\IndexController;
use App\Controllers\ReferenceController;
use App\Controllers\RoleController;
use App\Controllers\SettingsController;
use App\Controllers\TrackingController;
use App\Controllers\UserController;

$post = ['post' => true];

return [
    'login'  => [AuthController::class, 'login', ['public' => true]],
    'logout' => [AuthController::class, 'logout', $post],
    'profile' => [AuthController::class, 'profile'],
    'profile/password' => [AuthController::class, 'password', $post],

    'dashboard' => [DashboardController::class, 'index'],

    'animals'            => [AnimalController::class, 'index'],
    'animals/show'       => [AnimalController::class, 'show'],
    'animals/create'     => [AnimalController::class, 'create'],
    'animals/store'      => [AnimalController::class, 'store', $post],
    'animals/edit'       => [AnimalController::class, 'edit'],
    'animals/update'     => [AnimalController::class, 'update', $post],
    'animals/delete'     => [AnimalController::class, 'delete', $post],
    'animals/pdf'        => [AnimalController::class, 'pdf'],
    'animals/export'     => [AnimalController::class, 'export'],
    'animals/search'     => [AnimalController::class, 'search'],
    'animals/reclassify' => [AnimalController::class, 'reclassify', $post],
    'animals/compute'    => [AnimalController::class, 'compute', $post],

    'tracking'        => [TrackingController::class, 'index'],
    'tracking/create' => [TrackingController::class, 'create'],
    'tracking/store'  => [TrackingController::class, 'store', $post],
    'tracking/edit'   => [TrackingController::class, 'edit'],
    'tracking/update' => [TrackingController::class, 'update', $post],
    'tracking/delete' => [TrackingController::class, 'delete', $post],
    'tracking/export' => [TrackingController::class, 'export'],
    'tracking/pdf'    => [TrackingController::class, 'pdf'],

    'indices'           => [IndexController::class, 'index'],
    'indices/compute'   => [IndexController::class, 'compute', $post],
    'indices/results'   => [IndexController::class, 'results'],
    'indices/show'      => [IndexController::class, 'show'],
    'indices/evolution' => [IndexController::class, 'evolution'],
    'indices/export'    => [IndexController::class, 'export'],
    'indices/delete'    => [IndexController::class, 'delete', $post],

    'settings'             => [SettingsController::class, 'general'],
    'settings/save'        => [SettingsController::class, 'save', $post],
    'settings/indices'     => [SettingsController::class, 'indices'],
    'settings/index-form'  => [SettingsController::class, 'indexForm'],
    'settings/index-save'  => [SettingsController::class, 'indexSave', $post],
    'settings/index-delete'=> [SettingsController::class, 'indexDelete', $post],
    'settings/index-test'  => [SettingsController::class, 'indexTest', $post],
    'settings/system'      => [SettingsController::class, 'system'],
    'settings/cache-clear' => [SettingsController::class, 'cacheClear', $post],
    'settings/sync-perms'  => [SettingsController::class, 'syncPermissions', $post],

    'references'        => [ReferenceController::class, 'index'],
    'references/save'   => [ReferenceController::class, 'save', $post],
    'references/delete' => [ReferenceController::class, 'delete', $post],

    'users'        => [UserController::class, 'index'],
    'users/create' => [UserController::class, 'create'],
    'users/store'  => [UserController::class, 'store', $post],
    'users/edit'   => [UserController::class, 'edit'],
    'users/update' => [UserController::class, 'update', $post],
    'users/delete' => [UserController::class, 'delete', $post],
    'users/export' => [UserController::class, 'export'],

    'roles'        => [RoleController::class, 'index'],
    'roles/form'   => [RoleController::class, 'form'],
    'roles/save'   => [RoleController::class, 'save', $post],
    'roles/delete' => [RoleController::class, 'delete', $post],

    'audit'        => [AuditController::class, 'index'],
    'audit/show'   => [AuditController::class, 'show'],
    'audit/export' => [AuditController::class, 'export'],
];
