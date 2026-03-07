<?php

use App\Http\Controllers\Api\AdminClassSchedulingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/scheduling/masters', [AdminClassSchedulingController::class, 'masters']);
        Route::get('/scheduling/offerings', [AdminClassSchedulingController::class, 'offerings']);
        Route::post('/scheduling/offerings/upsert', [AdminClassSchedulingController::class, 'upsertOffering']);
        Route::post('/scheduling/items/bulk-upsert', [AdminClassSchedulingController::class, 'bulkUpsert']);
    });
});
