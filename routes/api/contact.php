<?php

use App\Http\Controllers\Api\ContactController;
use Illuminate\Support\Facades\Route;

Route::post('/contact/submit', [ContactController::class, 'submit']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/contact-messages', [ContactController::class, 'adminMessages']);
        Route::patch('/contact-messages/read-all', [ContactController::class, 'markAllRead']);
        Route::patch('/contact-messages/{id}/read', [ContactController::class, 'markRead']);
        Route::post('/contact-messages/{id}/reply', [ContactController::class, 'reply']);
    });
});
