<?php

use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\ProgramCatalogController;
use Illuminate\Support\Facades\Route;

Route::post('/admission/submit', [AdmissionController::class, 'submit']);
Route::get('/programs/catalog', [ProgramCatalogController::class, 'publicIndex']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/admissions', [AdmissionController::class, 'index']);
        Route::post('/admissions/{id}/approve', [AdmissionController::class, 'approve']);
        Route::post('/admissions/{id}/reject', [AdmissionController::class, 'reject']);
        Route::patch('/admissions/{id}/exam-status', [AdmissionController::class, 'updateExamStatus']);
        Route::post('/admissions/{id}/send-exam-schedule', [AdmissionController::class, 'sendExamSchedule']);
    });

    Route::prefix('student')->group(function () {
        Route::get('/profile/password-reminder', [AdmissionController::class, 'passwordReminder']);
        Route::post('/profile/change-password', [AdmissionController::class, 'updatePassword']);
    });
});
