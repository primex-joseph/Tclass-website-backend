<?php

use App\Http\Controllers\Api\FacultyPortalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('faculty')->group(function () {
        Route::get('/me', [FacultyPortalController::class, 'me']);
        Route::get('/periods', [FacultyPortalController::class, 'periods']);
        Route::get('/dashboard-summary', [FacultyPortalController::class, 'dashboardSummary']);
        Route::get('/class-schedules', [FacultyPortalController::class, 'classSchedules']);
        Route::patch('/class-schedules/{offeringId}', [FacultyPortalController::class, 'updateSchedule']);
        Route::get('/class-schedules/export', [FacultyPortalController::class, 'exportSchedules']);
        Route::get('/class-lists', [FacultyPortalController::class, 'classLists']);
        Route::get('/class-lists/{offeringId}/export', [FacultyPortalController::class, 'exportClassList']);
        Route::get('/classes', [FacultyPortalController::class, 'classes']);
        Route::post('/classes/{offeringId}/syllabus', [FacultyPortalController::class, 'uploadSyllabus']);
        Route::get('/students', [FacultyPortalController::class, 'students']);
        Route::get('/assignments', [FacultyPortalController::class, 'assignments']);
        Route::post('/assignments', [FacultyPortalController::class, 'storeAssignment']);
        Route::patch('/assignments/{assignmentId}', [FacultyPortalController::class, 'updateAssignment']);
        Route::delete('/assignments/{assignmentId}', [FacultyPortalController::class, 'destroyAssignment']);
        Route::get('/grade-sheets', [FacultyPortalController::class, 'gradeSheets']);
        Route::get('/grade-sheets/{offeringId}', [FacultyPortalController::class, 'gradeSheetDetail']);
        Route::post('/grade-sheets/{offeringId}/save', [FacultyPortalController::class, 'saveGradeSheet']);
        Route::post('/grade-sheets/{offeringId}/post', [FacultyPortalController::class, 'postGradeSheet']);
        Route::get('/grades', [FacultyPortalController::class, 'grades']);
    });
});
