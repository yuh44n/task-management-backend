<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskInteraction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TaskInteractionController extends Controller
{
    // ==================== COMMENTS ====================

    /**
     * Get comments for a task
     */
    public function getComments(Task $task): JsonResponse
    {
        $user = Auth::user();

        if (!$task->canView($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this task'
            ], 403);
        }

        $comments = $task->topLevelComments()
            ->with(['user', 'replies.user', 'attachments', 'replies.attachments'])
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
    public function storeComment(Request $request, Task $task): JsonResponse
    {
        $user = Auth::user();

        if (!$task->canView($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to comment on this task'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:task_interactions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract mentions from comment
        $taskInteraction = new TaskInteraction();
        $mentions = $taskInteraction->getMentionedUserIds($request->input('content'));

        $comment = TaskInteraction::createComment(
            $task->id,
            $user->id,
            $request->input('content'),
            $request->parent_id,
            $mentions
        );

        $comment->load(['user', 'parent.user']);

        // Create notifications for mentioned users
        foreach ($mentions as $mentionedUserId) {
            if ($mentionedUserId !== $user->id) {
                TaskInteraction::createNotification(
                    $mentionedUserId,
                    $task->id,
                    'You were mentioned in a comment',
                    "{$user->name} mentioned you in a comment on task: {$task->title}",
                    'comment_mention',
                    ['comment_id' => $comment->id, 'mentioned_by_name' => $user->name]
                );
            }
        }

        // Create notifications for task participants
        $this->notifyTaskParticipants($task, $user, 'task_comment', "{$user->name} commented on task: {$task->title}", ['comment_id' => $comment->id]);

        return response()->json([
            'success' => true,
            'comment' => $comment,
            'message' => 'Comment added successfully'
        ], 201);
    }

    /**
     * Update a comment
     */
    public function updateComment(Request $request, TaskInteraction $interaction): JsonResponse
    {
        $user = Auth::user();

        if (!$interaction->isComment()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid interaction type'
            ], 400);
        }

        if ($interaction->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this comment'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract mentions from updated comment
        $mentions = TaskInteraction::getMentionedUserIds($request->content);
        $oldMentions = $interaction->getMentions();

        $interaction->update([
            'content' => $request->content,
            'metadata' => array_merge($interaction->metadata ?? [], ['mentions' => $mentions]),
        ]);

        $interaction->load(['user', 'parent.user']);

        // Create notifications for newly mentioned users
        $newMentions = array_diff($mentions, $oldMentions);
        foreach ($newMentions as $mentionedUserId) {
            if ($mentionedUserId !== $user->id) {
                TaskInteraction::createNotification(
                    $mentionedUserId,
                    $interaction->task_id,
                    'You were mentioned in a comment',
                    "{$user->name} mentioned you in a comment on task: {$interaction->task->title}",
                    'comment_mention',
                    ['comment_id' => $interaction->id, 'mentioned_by_name' => $user->name]
                );
            }
        }

        return response()->json([
            'success' => true,
            'comment' => $interaction,
            'message' => 'Comment updated successfully'
        ]);
    }

    /**
     * Delete a comment
     */
    public function deleteComment(TaskInteraction $interaction): JsonResponse
    {
        $user = Auth::user();

        if (!$interaction->isComment()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid interaction type'
            ], 400);
        }

        if ($interaction->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this comment'
            ], 403);
        }

        $interaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    }

    // ==================== INVITATIONS ====================

    /**
     * Send invitation to collaborate on a task
     */
    public function sendInvitation(Request $request, Task $task): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invited_user_id' => 'required|exists:users,id',
            'role' => 'required|in:assignee,collaborator,viewer',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $invitedUserId = $request->invited_user_id;

        if (!$task->canEdit($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to invite users to this task'
            ], 403);
        }

        if ($invitedUserId === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot invite yourself to a task'
            ], 400);
        }

        if ($task->isAssignedTo($invitedUserId)) {
            return response()->json([
                'success' => false,
                'message' => 'User is already assigned to this task'
            ], 400);
        }

        // Check if invitation already exists
        $existingInvitation = $task->invitations()
            ->where('user_id', $invitedUserId)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation already exists for this user'
            ], 400);
        }

        $invitation = TaskInteraction::createInvitation(
            $task->id,
            $user->id,
            $invitedUserId,
            $request->role,
            $request->message
        );

        // Create notification for invited user
        TaskInteraction::createNotification(
            $invitedUserId,
            $task->id,
            'Task Invitation',
            "{$user->name} invited you to collaborate on task: {$task->title}",
            'task_invitation',
            ['invitation_id' => $invitation->id, 'inviter_name' => $user->name, 'role' => $request->role]
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully',
            'invitation' => $invitation->load(['user']),
        ], 201);
    }

    /**
     * Accept invitation
     */
    public function acceptInvitation(TaskInteraction $interaction): JsonResponse
    {
        $user = Auth::user();

        if (!$interaction->isInvitation()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid interaction type'
            ], 400);
        }

        if ($interaction->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only accept invitations sent to you'
            ], 403);
        }

        if (!$interaction->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has already been processed'
            ], 400);
        }

        $interaction->accept();

        // Create notification for task creator
        $task = $interaction->task;
        $inviterId = $interaction->metadata['invited_by'] ?? null;
        
        if ($inviterId && $inviterId !== $user->id) {
            TaskInteraction::createNotification(
                $inviterId,
                $task->id,
                'Invitation Accepted',
                "{$user->name} accepted your invitation to collaborate on task: {$task->title}",
                'invitation_accepted',
                ['invitation_id' => $interaction->id, 'accepted_by_name' => $user->name]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation accepted successfully',
            'invitation' => $interaction->load(['task', 'user']),
        ]);
    }

    /**
     * Decline invitation
     */
    public function declineInvitation(TaskInteraction $interaction): JsonResponse
    {
        $user = Auth::user();

        if (!$interaction->isInvitation()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid interaction type'
            ], 400);
        }

        if ($interaction->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only decline invitations sent to you'
            ], 403);
        }

        if (!$interaction->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has already been processed'
            ], 400);
        }

        $interaction->decline();

        // Create notification for task creator
        $task = $interaction->task;
        $inviterId = $interaction->metadata['invited_by'] ?? null;
        
        if ($inviterId && $inviterId !== $user->id) {
            TaskInteraction::createNotification(
                $inviterId,
                $task->id,
                'Invitation Declined',
                "{$user->name} declined your invitation to collaborate on task: {$task->title}",
                'invitation_declined',
                ['invitation_id' => $interaction->id, 'declined_by_name' => $user->name]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation declined successfully',
            'invitation' => $interaction->load(['task', 'user']),
        ]);
    }

    // Reminder methods have been removed

    // getReminders method has been removed

    // deleteReminder method has been removed
    
    // processDueReminders method has been removed

    // ==================== NOTIFICATIONS ====================

    /**
     * Get user's notifications
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);
        $type = $request->get('type');
        $status = $request->get('status');

        $query = $user->notifications();

        if ($type) {
            $query->where('metadata->notification_type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->getUnreadNotificationCount();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(TaskInteraction $interaction): JsonResponse
    {
        $user = Auth::user();

        if (!$interaction->isNotification()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid interaction type'
            ], 400);
        }

        if ($interaction->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only mark your own notifications as read'
            ], 403);
        }

        $interaction->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'notification' => $interaction,
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->markAllNotificationsAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get mentioned users for autocomplete
     */
    public function getMentionableUsers(Task $task): JsonResponse
    {
        $user = Auth::user();

        if (!$task->canView($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this task'
            ], 403);
        }

        $mentionableUsers = collect();
        
        if ($user->isAdmin()) {
            $mentionableUsers = User::query()->select('id', 'name', 'email')->get();
        } else {
            $mentionableUsers->push($task->creator);
            $mentionableUsers = $mentionableUsers->merge($task->assignedUsers);
            $mentionableUsers = $mentionableUsers->unique('id');
        }

        return response()->json([
            'success' => true,
            'users' => $mentionableUsers,
        ]);
    }

    /**
     * Get user's pending invitations
     */
    public function getPendingInvitations(Request $request): JsonResponse
    {
        $user = $request->user();
        $invitations = $user->pendingInvitations()
            ->with(['task'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'invitations' => $invitations,
        ]);
    }

    /**
     * Notify task participants
     */
    private function notifyTaskParticipants($task, $user, $type, $message, $metadata = [])
    {
        // Notify task creator if comment is not by them
        if ($task->created_by !== $user->id) {
            TaskInteraction::createNotification(
                $task->created_by,
                $task->id,
                'New comment on your task',
                $message,
                $type,
                array_merge($metadata, ['commenter_name' => $user->name])
            );
        }

        // Notify other task participants
        $taskParticipants = $task->assignedUsers()->where('user_id', '!=', $user->id)->get();
        foreach ($taskParticipants as $participant) {
            if ($participant->id !== $task->created_by) {
                TaskInteraction::createNotification(
                    $participant->id,
                    $task->id,
                    'New comment on task',
                    $message,
                    $type,
                    array_merge($metadata, ['commenter_name' => $user->name])
                );
            }
        }
    }
}
