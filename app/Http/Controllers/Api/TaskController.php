<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\TaskInteraction;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusUpdatedNotification;
use App\Notifications\TaskDeletedNotification;

class TaskController extends Controller
{
    /**
     * Get all tasks for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Task::with(['creator', 'assignedUsers', 'comments.user']);

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        // Filter by search term
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        // Get tasks based on user role
        if ($user->isAdmin()) {
            // Admin can see all tasks
            $tasks = $query->latest()->paginate(10);
        } else {
            // Regular users see tasks they created or are assigned to
            $tasks = $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('assignedUsers', function ($subQ) use ($user) {
                      $subQ->where('user_id', $user->id);
                  });
            })->latest()->paginate(10);
        }

        return response()->json([
            'success' => true,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Store a new task
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date|after:today',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'due_date' => $request->due_date,
            'created_by' => Auth::id(),
        ]);

        // Assign users to the task
        if ($request->has('assignees')) {
            foreach ($request->assignees as $userId) {
                TaskInteraction::createAssignment($task->id, $userId, 'assignee');

                // Create notification for assigned user
                TaskInteraction::createNotification(
                    $userId,
                    $task->id,
                    'Task Assigned',
                    "You have been assigned to task: {$task->title}",
                    'task_assigned',
                    ['assigned_by_name' => Auth::user()->name]
                );

                // Send email notification
                $assignedUser = User::find($userId);
                if ($assignedUser) {
                    $assignedUser->notify(new TaskAssignedNotification($task, Auth::user()));
                }
            }
        }

        $task->load(['creator', 'assignedUsers', 'comments.user']);

        return response()->json([
            'success' => true,
            'task' => $task,
            'message' => 'Task created successfully'
        ], 201);
    }

    /**
     * Get a specific task
     */
    public function show(Task $task): JsonResponse
    {
        $user = Auth::user();

        // Check if user can view this task
        if (!$task->canView($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this task'
            ], 403);
        }

        $task->load(['creator', 'assignedUsers', 'comments.user']);

        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }

    /**
     * Update a task
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $user = Auth::user();

        // Check if user can update this task
        if (!$task->canEdit($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this task'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|required|in:low,medium,high',
            'status' => 'sometimes|required|in:pending,in_progress,completed',
            'due_date' => 'nullable|date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Store old values for comparison
        $oldStatus = $task->status;
        $oldAssignees = $task->assignedUsers->pluck('id')->toArray();

        $task->update($request->only(['title', 'description', 'priority', 'status', 'due_date']));

        // Update assignees if provided
        if ($request->has('assignees')) {
            // Remove existing assignments
            $task->assignments()->delete();
            
            // Add new assignments
            foreach ($request->assignees as $userId) {
                TaskInteraction::createAssignment($task->id, $userId, 'assignee');
            }

            // Create notifications for newly assigned users
            $newAssignees = array_diff($request->assignees, $oldAssignees);
            foreach ($newAssignees as $userId) {
                TaskInteraction::createNotification(
                    $userId,
                    $task->id,
                    'Task Assigned',
                    "You have been assigned to task: {$task->title}",
                    'task_assigned',
                    ['assigned_by_name' => $user->name]
                );

                // Send email notification
                $assignedUser = User::find($userId);
                if ($assignedUser) {
                    $assignedUser->notify(new TaskAssignedNotification($task, $user));
                }
            }
        }

        // Create notifications for task updates
        if ($oldStatus !== $task->status) {
            // Notify task creator if status changed by someone else
            if ($task->created_by !== $user->id) {
                TaskInteraction::createNotification(
                    $task->created_by,
                    $task->id,
                    'Task Status Updated',
                    "{$user->name} updated the status of task '{$task->title}' to {$task->status}",
                    'task_status_changed',
                    ['updated_by_name' => $user->name, 'old_status' => $oldStatus, 'new_status' => $task->status]
                );

                // Send email notification
                $taskCreator = User::find($task->created_by);
                if ($taskCreator) {
                    $taskCreator->notify(new TaskStatusUpdatedNotification($task, $user, $oldStatus, $task->status));
                }
            }

            // Notify other task participants
            $taskParticipants = $task->assignedUsers()->where('user_id', '!=', $user->id)->get();
            foreach ($taskParticipants as $participant) {
                if ($participant->id !== $task->created_by) {
                    TaskInteraction::createNotification(
                        $participant->id,
                        $task->id,
                        'Task Status Updated',
                        "{$user->name} updated the status of task '{$task->title}' to {$task->status}",
                        'task_status_changed',
                        ['updated_by_name' => $user->name, 'old_status' => $oldStatus, 'new_status' => $task->status]
                    );

                    // Send email notification
                    $participant->notify(new TaskStatusUpdatedNotification($task, $user, $oldStatus, $task->status));
                }
            }
        }

        $task->load(['creator', 'assignedUsers', 'comments.user']);

        return response()->json([
            'success' => true,
            'task' => $task,
            'message' => 'Task updated successfully'
        ]);
    }

    /**
     * Delete a task
     */
    public function destroy(Task $task): JsonResponse
    {
        $user = Auth::user();

        // Check if user can delete this task
        if (!$user->isAdmin() && $task->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this task'
            ], 403);
        }

        // Create notifications for task participants before deletion
        $taskParticipants = $task->assignedUsers()->where('user_id', '!=', $user->id)->get();
        foreach ($taskParticipants as $participant) {
            TaskInteraction::createNotification(
                $participant->id,
                $task->id,
                'Task Deleted',
                "{$user->name} deleted the task: {$task->title}",
                'task_deleted',
                ['deleted_by_name' => $user->name]
            );

            // Send email notification
            $participant->notify(new TaskDeletedNotification($task->title, $user));
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully'
        ]);
    }

    /**
     * Get all users for assignment
     */
    public function getUsers(): JsonResponse
    {
        $users = User::select('id', 'name', 'email')->get();
        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }
} 