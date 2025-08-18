<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskInteractionController;
use App\Http\Controllers\FileAttachmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::middleware('cors')->group(function () {
    Route::match(['post', 'options'], '/register', [AuthController::class, 'register']);
    Route::match(['post', 'options'], '/login', [AuthController::class, 'login']);
});

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Task routes
    Route::apiResource('tasks', TaskController::class);
    Route::get('/tasks/users/list', [TaskController::class, 'getUsers']);

    // Task Interactions (Comments, Invitations, Notifications, Reminders)
    Route::prefix('tasks/{task}')->group(function () {
        // Comments
        Route::get('/comments', [TaskInteractionController::class, 'getComments']);
        Route::post('/comments', [TaskInteractionController::class, 'storeComment']);
        Route::get('/mentionable-users', [TaskInteractionController::class, 'getMentionableUsers']);
        
        // Invitations
        Route::post('/invitations', [TaskInteractionController::class, 'sendInvitation']);
        
        // Reminders routes have been removed
        
        // File Attachments
        Route::post('/attachments', [FileAttachmentController::class, 'upload']);
        Route::get('/attachments', [FileAttachmentController::class, 'getTaskAttachments']);
    });

    // Individual interactions
    Route::prefix('interactions')->group(function () {
        // Comments
        Route::put('/{interaction}/comment', [TaskInteractionController::class, 'updateComment']);
        Route::delete('/{interaction}/comment', [TaskInteractionController::class, 'deleteComment']);
        
        // Invitations
        Route::post('/{interaction}/accept', [TaskInteractionController::class, 'acceptInvitation']);
        Route::post('/{interaction}/decline', [TaskInteractionController::class, 'declineInvitation']);
        
        // Reminder routes have been removed
        
        // Notifications
        Route::patch('/{interaction}/read', [TaskInteractionController::class, 'markAsRead']);
        
        // File Attachments
        Route::get('/{interaction}/attachments', [FileAttachmentController::class, 'getInteractionAttachments']);
    });
    
    // File Attachments
    Route::delete('/attachments/{attachment}', [FileAttachmentController::class, 'delete']);

    // User-specific routes
    Route::prefix('user')->group(function () {
        // Invitations
        Route::get('/invitations/pending', [TaskInteractionController::class, 'getPendingInvitations']);
        
        // Reminder routes have been removed
        
        // Notifications
        Route::get('/notifications', [TaskInteractionController::class, 'getNotifications']);
        Route::get('/notifications/unread-count', [TaskInteractionController::class, 'getUnreadCount']);
        Route::patch('/notifications/mark-all-read', [TaskInteractionController::class, 'markAllAsRead']);
    });

    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::get('/tasks', [AdminController::class, 'getAllTasks']);
        Route::get('/stats', [AdminController::class, 'getDashboardStats']);
        Route::put('/users/{user}/role', [AdminController::class, 'updateUserRole']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
    });
    
    // System routes for reminders have been removed
});
