<?php

declare(strict_types=1);

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

/*
| Installer routes (prefix "install/", name "install.").
| Reachable only while the app is NOT installed — EnsureInstalled redirects
| here pre-install and returns 404 for every /install/* once installed.
*/

Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
Route::get('/requirements', [InstallController::class, 'requirements'])->name('requirements');

Route::get('/environment', [InstallController::class, 'environment'])->name('environment');
Route::post('/environment', [InstallController::class, 'storeEnvironment'])->name('environment.store');

Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
Route::post('/admin', [InstallController::class, 'storeAdmin'])->name('admin.store');

Route::get('/review', [InstallController::class, 'review'])->name('review');
Route::post('/install', [InstallController::class, 'install'])->name('install');
