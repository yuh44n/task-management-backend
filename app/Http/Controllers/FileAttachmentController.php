<?php

namespace App\Http\Controllers;

use App\Models\FileAttachment;
use App\Models\Task;
use App\Models\TaskInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileAttachmentController extends Controller
{
    /**
     * Upload a file attachment to a task
     */
    public function upload(Request $request, Task $task)
    {
        // Log the request for debugging
        \Illuminate\Support\Facades\Log::info('File upload request received', [
            'task_id' => $task->id,
            'user_id' => Auth::id(),
            'has_file' => $request->hasFile('file'),
            'interaction_id' => $request->input('interaction_id')
        ]);

        // Validate request
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpeg,png,gif,jpg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,7z', // 10MB max, specific file types only
            'interaction_id' => 'nullable|exists:task_interactions,id',
        ]);

        // Additional security check - prevent PHP files and other dangerous file types
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'inc', 'pl', 'py', 'rb', 'sh', 'bat', 'cmd', 'exe', 'dll', 'so'];
        
        if (in_array($extension, $dangerousExtensions)) {
            \Illuminate\Support\Facades\Log::warning('Dangerous file upload attempt blocked', [
                'user_id' => Auth::id(), 
                'task_id' => $task->id,
                'filename' => $file->getClientOriginalName(),
                'extension' => $extension
            ]);
            return response()->json(['message' => 'File type not allowed for security reasons'], 400);
        }

        // Check if user can access this task
        if (!$task->canView(Auth::id())) {
            \Illuminate\Support\Facades\Log::warning('Unauthorized access attempt', ['user_id' => Auth::id(), 'task_id' => $task->id]);
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if interaction belongs to this task
        if ($request->has('interaction_id')) {
            $interaction = TaskInteraction::find($request->interaction_id);
            if (!$interaction || $interaction->task_id !== $task->id) {
                \Illuminate\Support\Facades\Log::warning('Invalid interaction', ['interaction_id' => $request->interaction_id, 'task_id' => $task->id]);
                return response()->json(['message' => 'Invalid interaction'], 400);
            }
        }

        try {
            // Get the file from the request
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            \Illuminate\Support\Facades\Log::info('File details', [
                'original_name' => $originalFilename,
                'mime_type' => $mimeType,
                'size' => $fileSize
            ]);

            // Generate a unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Ensure directory exists
            $directory = 'attachments/' . $task->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
                \Illuminate\Support\Facades\Log::info('Created directory', ['directory' => $directory]);
            }
            
            // Store the file
            $path = $file->storeAs($directory, $filename, 'public');
            \Illuminate\Support\Facades\Log::info('File stored', ['path' => $path]);

            if (!$path) {
                \Illuminate\Support\Facades\Log::error('Failed to store file');
                return response()->json(['message' => 'Failed to store file'], 500);
            }

            // Create file attachment record
            $attachment = FileAttachment::create([
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'interaction_id' => $request->interaction_id,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'file_path' => $path,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
            ]);

            // Load the user relationship for the response
            $attachment->load('user');

            \Illuminate\Support\Facades\Log::info('Attachment created', ['attachment_id' => $attachment->id]);

            return response()->json([
                'message' => 'File uploaded successfully',
                'attachment' => [
                    'id' => $attachment->id,
                    'filename' => $attachment->original_filename,
                    'size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                    'url' => $attachment->url,
                    'uploaded_by' => [
                        'id' => $attachment->user->id,
                        'name' => $attachment->user->name,
                    ],
                    'uploaded_at' => $attachment->created_at,
                    'interaction_id' => $attachment->interaction_id,
                ],
                'url' => $attachment->url,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error uploading file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error uploading file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all attachments for a task
     */
    public function getTaskAttachments(Task $task)
    {
        // Check if user can access this task
        if (!$task->canView(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $attachments = $task->attachments()->with('user')->get();

        return response()->json([
            'attachments' => $attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->original_filename,
                    'size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                    'url' => $attachment->url,
                    'uploaded_by' => [
                        'id' => $attachment->user->id,
                        'name' => $attachment->user->name,
                    ],
                    'uploaded_at' => $attachment->created_at,
                    'interaction_id' => $attachment->interaction_id,
                ];
            }),
        ]);
    }

    /**
     * Get attachments for a specific interaction
     */
    public function getInteractionAttachments(TaskInteraction $interaction)
    {
        // Check if user can access this task
        if (!$interaction->task->canView(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $attachments = $interaction->attachments()->with('user')->get();

        return response()->json([
            'attachments' => $attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->original_filename,
                    'size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                    'url' => $attachment->url,
                    'uploaded_by' => [
                        'id' => $attachment->user->id,
                        'name' => $attachment->user->name,
                    ],
                    'uploaded_at' => $attachment->created_at,
                ];
            }),
        ]);
    }

    /**
     * Delete a file attachment
     */
    public function delete(FileAttachment $attachment)
    {
        // Check if user can delete this attachment
        if ($attachment->user_id !== Auth::id() && !$attachment->task->canEdit(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete the file from storage
        Storage::disk('public')->delete($attachment->file_path);

        // Delete the attachment record
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully']);
    }
}