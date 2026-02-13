<?php

use App\Http\Controllers\Api\AdminEnrollmentController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentEnrollmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::post('/admission/submit', [AdmissionController::class, 'submit']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('student')->group(function () {
        Route::get('/periods', [StudentEnrollmentController::class, 'periods']);
        Route::get('/curriculum-evaluation', [StudentEnrollmentController::class, 'curriculumEvaluation']);
        Route::get('/courses', [StudentEnrollmentController::class, 'courses']);
        Route::get('/enrollments/pre-enlisted', [StudentEnrollmentController::class, 'preEnlisted']);
        Route::get('/enrollments/enrolled-subjects', [StudentEnrollmentController::class, 'enrolledSubjects']);
        Route::post('/enrollments/add', [StudentEnrollmentController::class, 'add']);
        Route::post('/enrollments/auto', [StudentEnrollmentController::class, 'auto']);
        Route::delete('/enrollments/{enrollmentId}', [StudentEnrollmentController::class, 'remove']);
        Route::delete('/enrollments', [StudentEnrollmentController::class, 'clearDraft']);
        Route::post('/enrollments/assess', [StudentEnrollmentController::class, 'assess']);
    });

    Route::prefix('admin')->group(function () {
        Route::get('/enrollments', [AdminEnrollmentController::class, 'index']);
        Route::patch('/enrollments/{enrollmentId}', [AdminEnrollmentController::class, 'updateStatus']);
        Route::patch('/enrollment-periods/{periodId}/activate', [AdminEnrollmentController::class, 'activatePeriod']);
        Route::get('/admissions', [AdmissionController::class, 'index']);
        Route::post('/admissions/{id}/approve', [AdmissionController::class, 'approve']);
        Route::post('/admissions/{id}/reject', [AdmissionController::class, 'reject']);
    });

    Route::prefix('student')->group(function () {
        Route::get('/profile/password-reminder', [AdmissionController::class, 'passwordReminder']);
        Route::post('/profile/change-password', [AdmissionController::class, 'updatePassword']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
