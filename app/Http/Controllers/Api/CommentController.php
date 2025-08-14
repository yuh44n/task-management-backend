<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Get comments for a task
     */
    public function index(Task $task): JsonResponse
    {
        $user = Auth::user();

        // Check if user can view this task
        if (!$task->canView($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this task'
            ], 403);
        }

        $comments = $task->comments()
            ->topLevel()
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'comments' => $comments,
        ]);
    }

    /**
     * Store a new comment
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        $user = Auth::user();

        // Check if user can comment on this task
        if (!$task->canView($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to comment on this task'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:task_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract mentions from comment
        $mentions = TaskComment::getMentionedUserIds($request->comment);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'parent_id' => $request->parent_id,
            'comment' => $request->comment,
            'mentions' => $mentions,
        ]);

        $comment->load(['user', 'parent.user']);

        // Create notifications for mentioned users
        foreach ($mentions as $mentionedUserId) {
            if ($mentionedUserId !== $user->id) {
                Notification::create([
                    'user_id' => $mentionedUserId,
                    'type' => 'comment_mention',
                    'title' => 'You were mentioned in a comment',
                    'message' => "{$user->name} mentioned you in a comment on task: {$task->title}",
                    'data' => [
                        'task_id' => $task->id,
                        'comment_id' => $comment->id,
                        'mentioned_by_name' => $user->name,
                    ],
                ]);
            }
        }

        // Create notification for task creator if comment is not by them
        if ($task->created_by !== $user->id) {
            Notification::create([
                'user_id' => $task->created_by,
                'type' => 'task_comment',
                'title' => 'New comment on your task',
                'message' => "{$user->name} commented on your task: {$task->title}",
                'data' => [
                    'task_id' => $task->id,
                    'comment_id' => $comment->id,
                    'commenter_name' => $user->name,
                ],
            ]);
        }

        // Create notifications for other task participants
        $taskParticipants = $task->assignedUsers()->where('user_id', '!=', $user->id)->get();
        foreach ($taskParticipants as $participant) {
            if ($participant->id !== $task->created_by) {
                Notification::create([
                    'user_id' => $participant->id,
                    'type' => 'task_comment',
                    'title' => 'New comment on task',
                    'message' => "{$user->name} commented on task: {$task->title}",
                    'data' => [
                        'task_id' => $task->id,
                        'comment_id' => $comment->id,
                        'commenter_name' => $user->name,
                    ],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'comment' => $comment,
            'message' => 'Comment added successfully'
        ], 201);
    }

    /**
     * Update a comment
     */
    public function update(Request $request, TaskComment $comment): JsonResponse
    {
        $user = Auth::user();

        // Check if user can update this comment
        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this comment'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract mentions from updated comment
        $mentions = TaskComment::getMentionedUserIds($request->comment);
        $oldMentions = $comment->mentions ?? [];

        $comment->update([
            'comment' => $request->comment,
            'mentions' => $mentions,
        ]);

        $comment->load(['user', 'parent.user']);

        // Create notifications for newly mentioned users
        $newMentions = array_diff($mentions, $oldMentions);
        foreach ($newMentions as $mentionedUserId) {
            if ($mentionedUserId !== $user->id) {
                Notification::create([
                    'user_id' => $mentionedUserId,
                    'type' => 'comment_mention',
                    'title' => 'You were mentioned in a comment',
                    'message' => "{$user->name} mentioned you in a comment on task: {$comment->task->title}",
                    'data' => [
                        'task_id' => $comment->task_id,
                        'comment_id' => $comment->id,
                        'mentioned_by_name' => $user->name,
                    ],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'comment' => $comment,
            'message' => 'Comment updated successfully'
        ]);
    }

    /**
     * Delete a comment
     */
    public function destroy(TaskComment $comment): JsonResponse
    {
        $user = Auth::user();

        // Check if user can delete this comment
        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this comment'
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    }

    /**
     * Get replies to a comment
     */
    public function getReplies(TaskComment $comment): JsonResponse
    {
        $user = Auth::user();

        // Check if user can view the task
        if (!$comment->task->canView($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this task'
            ], 403);
        }

        $replies = $comment->replies()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'replies' => $replies,
        ]);
    }

    /**
     * Get mentioned users for autocomplete
     */
    public function getMentionableUsers(Task $task): JsonResponse
    {
        $user = Auth::user();

        // Check if user can view this task
        if (!$task->canView($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this task'
            ], 403);
        }

        // Get all users who can be mentioned (task participants + all users for admins)
        $mentionableUsers = collect();
        
        if ($user->isAdmin()) {
            $mentionableUsers = User::select('id', 'name', 'email')->get();
        } else {
            // Task creator
            $mentionableUsers->push($task->creator);
            
            // Task participants
            $mentionableUsers = $mentionableUsers->merge($task->assignedUsers);
            
            // Remove duplicates
            $mentionableUsers = $mentionableUsers->unique('id');
        }

        return response()->json([
            'success' => true,
            'users' => $mentionableUsers,
        ]);
    }
} 