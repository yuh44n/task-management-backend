<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileAttachmentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Non-prefixed routes for file attachments (to handle requests without /api prefix)
Route::middleware('auth:sanctum')->group(function () {
    // File Attachments routes
    Route::prefix('tasks/{task}')->group(function () {
        Route::post('/attachments', [FileAttachmentController::class, 'upload']);
        Route::get('/attachments', [FileAttachmentController::class, 'getTaskAttachments']);
    });
    
    // Additional routes for attachments
    Route::get('/interactions/{interaction}/attachments', [FileAttachmentController::class, 'getInteractionAttachments']);
    Route::delete('/attachments/{attachment}', [FileAttachmentController::class, 'delete']);
});
