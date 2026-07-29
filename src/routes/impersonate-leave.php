<?php

use Illuminate\Support\Facades\Route;
use Kolydart\Laravel\App\Http\Controllers\ImpersonateController;

Route::post('users/leave-impersonation', [ImpersonateController::class, 'leaveImpersonation'])->name('users.leaveImpersonation');
