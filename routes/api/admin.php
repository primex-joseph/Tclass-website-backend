<?php

use App\Http\Controllers\Api\AdminCurriculumController;
use App\Http\Controllers\Api\AdminEnrollmentController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\ProgramCatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdmissionController::class, 'users']);
        Route::get('/students', [AdmissionController::class, 'students']);
        Route::post('/users', [AdmissionController::class, 'createPortalUser']);
        Route::get('/dashboard-stats', [AdmissionController::class, 'dashboardStats']);
        Route::get('/departments-overview', [AdmissionController::class, 'departmentOverview']);

        Route::get('/enrollments', [AdminEnrollmentController::class, 'index']);
        Route::patch('/enrollments/{enrollmentId}', [AdminEnrollmentController::class, 'updateStatus']);
        Route::patch('/enrollment-periods/{periodId}/activate', [AdminEnrollmentController::class, 'activatePeriod']);
        Route::post('/enrollment-periods/rollover', [AdminEnrollmentController::class, 'rolloverPeriod']);

        Route::get('/curricula', [AdminCurriculumController::class, 'index']);
        Route::get('/curricula/{curriculumId}/subjects', [AdminCurriculumController::class, 'subjects']);
        Route::post('/curricula', [AdminCurriculumController::class, 'store']);
        Route::patch('/curricula/{curriculumId}/activate', [AdminCurriculumController::class, 'activate']);

        Route::get('/programs', [ProgramCatalogController::class, 'index']);
        Route::post('/programs', [ProgramCatalogController::class, 'store']);
        Route::patch('/programs/{program}', [ProgramCatalogController::class, 'update']);
        Route::delete('/programs/{program}', [ProgramCatalogController::class, 'destroy']);
    });
});

