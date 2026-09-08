<?php

use Illuminate\Support\Facades\Route;
use Kolydart\Laravel\App\Http\Controllers\MediaController;

Route::get('media/{media:uuid}', [MediaController::class, 'media'])
    ->name('media');

/*
 * The conversion name ends up in a filesystem path, so it is constrained to the
 * registered set at the router rather than validated in the controller.
 */
Route::get('media/{media:uuid}/conversion/{conversion}', [MediaController::class, 'conversion'])
    ->where('conversion', implode('|', config('kolydart.media.conversions', ['thumb', 'preview'])))
    ->name('media.conversion');
