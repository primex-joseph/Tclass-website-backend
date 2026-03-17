<?php

use App\Http\Controllers\Api\AdminCurriculumController;
use App\Http\Controllers\Api\AdminEnrollmentController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\FacultyRbacController;
use App\Http\Controllers\Api\ProgramCatalogController;
use App\Http\Controllers\Api\QuizController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdmissionController::class, 'users']);
        Route::get('/students', [AdmissionController::class, 'students']);
        Route::post('/users', [AdmissionController::class, 'createPortalUser']);
        Route::get('/dashboard-stats', [AdmissionController::class, 'dashboardStats']);
        Route::get('/departments-overview', [AdmissionController::class, 'departmentOverview']);
        Route::get('/faculty/positions', [FacultyRbacController::class, 'positions']);
        Route::get('/rbac/faculty', [FacultyRbacController::class, 'index']);
        Route::patch('/rbac/faculty/templates/{templateKey}', [FacultyRbacController::class, 'updateTemplate']);
        Route::patch('/rbac/faculty/users/{userId}', [FacultyRbacController::class, 'updateUser']);

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

        Route::get('/quizzes', [QuizController::class, 'index'])->defaults('scope', 'admin');
        Route::post('/quizzes', [QuizController::class, 'store'])->defaults('scope', 'admin');
        Route::get('/quizzes/creators', [QuizController::class, 'creators'])->defaults('scope', 'admin');
        Route::get('/quizzes/offerings/catalog', [QuizController::class, 'offeringsCatalog'])->defaults('scope', 'admin');
        Route::get('/quizzes/entrance/courses', [QuizController::class, 'entranceCourses'])->defaults('scope', 'admin');
        Route::get('/quizzes/{id}', [QuizController::class, 'show'])->defaults('scope', 'admin');
        Route::patch('/quizzes/{id}', [QuizController::class, 'update'])->defaults('scope', 'admin');
        Route::delete('/quizzes/{id}', [QuizController::class, 'destroy'])->defaults('scope', 'admin');
        Route::post('/quizzes/{id}/publish', [QuizController::class, 'publish'])->defaults('scope', 'admin');
        Route::get('/quizzes/{quizId}/items', [QuizController::class, 'listItems'])->defaults('scope', 'admin');
        Route::post('/quizzes/{quizId}/items', [QuizController::class, 'storeItem'])->defaults('scope', 'admin');
        Route::patch('/quizzes/{quizId}/items/{itemId}', [QuizController::class, 'updateItem'])->defaults('scope', 'admin');
        Route::delete('/quizzes/{quizId}/items/{itemId}', [QuizController::class, 'destroyItem'])->defaults('scope', 'admin');
        Route::get('/quizzes/{quizId}/results', [QuizController::class, 'listResults'])->defaults('scope', 'admin');
        Route::get('/quizzes/{quizId}/results/{attemptId}', [QuizController::class, 'resultDetail'])->defaults('scope', 'admin');
        Route::get('/quizzes/{quizId}/preview', [QuizController::class, 'preview'])->defaults('scope', 'admin');
        Route::post('/quizzes/{quizId}/preview/submit', [QuizController::class, 'previewSubmit'])->defaults('scope', 'admin');
    });
});
