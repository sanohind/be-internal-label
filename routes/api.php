<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\ErpSyncController;
use App\Http\Controllers\Api\LabelController;

Route::get('/sync/prod-header', [ErpSyncController::class, 'syncProdHeaders']);
Route::get('/sync/prod-label', [ErpSyncController::class, 'syncProdLabels']);

// Label Printing APIs
Route::get('/labels/prod-headers', [LabelController::class, 'listProdHeaders']);
Route::get('/labels/printable', [LabelController::class, 'getPrintableLabels']);
Route::post('/labels/mark-printed', [LabelController::class, 'markAsPrinted']);
