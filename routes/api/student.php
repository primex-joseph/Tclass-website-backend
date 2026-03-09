<?php

use App\Http\Controllers\Api\StudentEnrollmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('student')->group(function () {
        Route::get('/dashboard-summary', [StudentEnrollmentController::class, 'dashboardSummary']);
        Route::get('/periods', [StudentEnrollmentController::class, 'periods']);
        Route::get('/curriculum-evaluation', [StudentEnrollmentController::class, 'curriculumEvaluation']);
        Route::get('/courses', [StudentEnrollmentController::class, 'courses']);
        Route::get('/enrollment-history', [StudentEnrollmentController::class, 'enrollmentHistory']);
        Route::get('/enrollment-offerings', [StudentEnrollmentController::class, 'enrollmentOfferings']);
        Route::get('/enrollments/pre-enlisted', [StudentEnrollmentController::class, 'preEnlisted']);
        Route::get('/enrollments/enrolled-subjects', [StudentEnrollmentController::class, 'enrolledSubjects']);
        Route::post('/enrollments/add', [StudentEnrollmentController::class, 'add']);
        Route::post('/enrollments/auto', [StudentEnrollmentController::class, 'auto']);
        Route::delete('/enrollments/{enrollmentId}', [StudentEnrollmentController::class, 'remove']);
        Route::delete('/enrollments', [StudentEnrollmentController::class, 'clearDraft']);
        Route::post('/enrollments/assess', [StudentEnrollmentController::class, 'assess']);
    });
});
