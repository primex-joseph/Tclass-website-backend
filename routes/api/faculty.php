<?php

use App\Http\Controllers\Api\FacultyPortalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('faculty')->group(function () {
        Route::get('/me', [FacultyPortalController::class, 'me']);
        Route::get('/periods', [FacultyPortalController::class, 'periods']);
        Route::get('/dashboard-summary', [FacultyPortalController::class, 'dashboardSummary']);
        Route::get('/class-schedules', [FacultyPortalController::class, 'classSchedules']);
        Route::get('/class-schedules/masters', [FacultyPortalController::class, 'classScheduleMasters']);
        Route::get('/class-schedules/rooms', [FacultyPortalController::class, 'classScheduleRooms']);
        Route::post('/class-schedules/rooms', [FacultyPortalController::class, 'storeClassScheduleRoom']);
        Route::patch('/class-schedules/rooms/{roomId}', [FacultyPortalController::class, 'updateClassScheduleRoom']);
        Route::delete('/class-schedules/rooms/{roomId}', [FacultyPortalController::class, 'destroyClassScheduleRoom']);
        Route::get('/class-schedules/rooms/availability', [FacultyPortalController::class, 'classScheduleRoomAvailability']);
        Route::patch('/class-schedules/{offeringId}', [FacultyPortalController::class, 'updateSchedule']);
        Route::get('/class-schedules/{offeringId}/students', [FacultyPortalController::class, 'offeringStudents']);
        Route::get('/class-schedules/{offeringId}/students/search', [FacultyPortalController::class, 'searchStudentsForOffering']);
        Route::post('/class-schedules/{offeringId}/students', [FacultyPortalController::class, 'addStudentToOffering']);
        Route::patch('/class-schedules/{offeringId}/students/{enrollmentId}', [FacultyPortalController::class, 'updateOfferingEnrollmentStatus']);
        Route::delete('/class-schedules/{offeringId}/students/{enrollmentId}', [FacultyPortalController::class, 'removeStudentFromOffering']);
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
