<?php

use Illuminate\Support\Facades\Route;
use Kolydart\Laravel\App\Http\Controllers\GuideController;
use Kolydart\Laravel\App\Support\Guide\GuideRepository;

Route::get('guide', [GuideController::class, 'index'])
    ->name('guide.index');

// Before {slug}: `assets` is not a slug, and the order keeps it from being read as one.
Route::get('guide/assets/{path}', [GuideController::class, 'asset'])
    ->where('path', '.*')
    ->name('guide.asset');

Route::get('guide/{slug}', [GuideController::class, 'show'])
    ->where('slug', trim(GuideRepository::SLUG_PATTERN, '/^$'))
    ->name('guide.show');
