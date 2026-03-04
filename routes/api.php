<?php

use App\Http\Controllers\Api\AdminEnrollmentController;
use App\Http\Controllers\Api\AdminCurriculumController;
use App\Http\Controllers\Api\AdminClassSchedulingController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\StudentEnrollmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password/check-email', [AuthController::class, 'checkForgotPasswordEmail']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/forgot-password/verify-code', [AuthController::class, 'verifyResetCode']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::post('/admission/submit', [AdmissionController::class, 'submit']);
Route::post('/contact/submit', [ContactController::class, 'submit']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('student')->group(function () {
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

    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdmissionController::class, 'users']);
        Route::post('/users', [AdmissionController::class, 'createPortalUser']);
        Route::get('/dashboard-stats', [AdmissionController::class, 'dashboardStats']);
        Route::get('/departments-overview', [AdmissionController::class, 'departmentOverview']);
        Route::get('/contact-messages', [ContactController::class, 'adminMessages']);
        Route::patch('/contact-messages/read-all', [ContactController::class, 'markAllRead']);
        Route::patch('/contact-messages/{id}/read', [ContactController::class, 'markRead']);
        Route::post('/contact-messages/{id}/reply', [ContactController::class, 'reply']);
        Route::get('/enrollments', [AdminEnrollmentController::class, 'index']);
        Route::patch('/enrollments/{enrollmentId}', [AdminEnrollmentController::class, 'updateStatus']);
        Route::patch('/enrollment-periods/{periodId}/activate', [AdminEnrollmentController::class, 'activatePeriod']);
        Route::post('/enrollment-periods/rollover', [AdminEnrollmentController::class, 'rolloverPeriod']);
        Route::get('/curricula', [AdminCurriculumController::class, 'index']);
        Route::get('/curricula/{curriculumId}/subjects', [AdminCurriculumController::class, 'subjects']);
        Route::post('/curricula', [AdminCurriculumController::class, 'store']);
        Route::patch('/curricula/{curriculumId}/activate', [AdminCurriculumController::class, 'activate']);
        Route::get('/admissions', [AdmissionController::class, 'index']);
        Route::post('/admissions/{id}/approve', [AdmissionController::class, 'approve']);
        Route::post('/admissions/{id}/reject', [AdmissionController::class, 'reject']);
        Route::patch('/admissions/{id}/exam-status', [AdmissionController::class, 'updateExamStatus']);
        Route::post('/admissions/{id}/send-exam-schedule', [AdmissionController::class, 'sendExamSchedule']);

        Route::get('/scheduling/masters', [AdminClassSchedulingController::class, 'masters']);
        Route::get('/scheduling/offerings', [AdminClassSchedulingController::class, 'offerings']);
        Route::post('/scheduling/offerings/upsert', [AdminClassSchedulingController::class, 'upsertOffering']);
        Route::post('/scheduling/items/bulk-upsert', [AdminClassSchedulingController::class, 'bulkUpsert']);
    });

    Route::prefix('student')->group(function () {
        Route::get('/profile/password-reminder', [AdmissionController::class, 'passwordReminder']);
        Route::post('/profile/change-password', [AdmissionController::class, 'updatePassword']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
