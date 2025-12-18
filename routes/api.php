<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ErpSyncController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\AuthController;

// Public routes - Authentication
Route::post('/login', [AuthController::class, 'login']);

// Protected routes - Require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ERP Sync routes
    Route::get('/sync/prod-header', [ErpSyncController::class, 'syncProdHeaders']);
    Route::get('/sync/prod-label', [ErpSyncController::class, 'syncProdLabels']);
    Route::post('/sync/manual', [ErpSyncController::class, 'syncManual']); // Manual sync via queue

    // Label Printing routes
    Route::get('/labels/prod-headers', [LabelController::class, 'listProdHeaders']);
    Route::get('/labels/printable', [LabelController::class, 'getPrintableLabels']);
    Route::post('/labels/mark-printed', [LabelController::class, 'markAsPrinted']);
});

