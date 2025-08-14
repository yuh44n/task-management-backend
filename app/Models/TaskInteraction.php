<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'type',
        'role',
        'content',
        'metadata',
        'status',
        'parent_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the task for this interaction
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user for this interaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent interaction (for replies)
     */
    public function parent()
    {
        return $this->belongsTo(TaskInteraction::class, 'parent_id');
    }

    /**
     * Get replies to this interaction
     */
    public function replies()
    {
        return $this->hasMany(TaskInteraction::class, 'parent_id');
    }

    // Assignment Methods
    public function scopeAssignments($query)
    {
        return $query->where('type', 'assignment');
    }

    public function isAssignment(): bool
    {
        return $this->type === 'assignment';
    }

    // Comment Methods
    public function scopeComments($query)
    {
        return $query->where('type', 'comment');
    }

    public function scopeTopLevelComments($query)
    {
        return $query->where('type', 'comment')->whereNull('parent_id');
    }

    public function isComment(): bool
    {
        return $this->type === 'comment';
    }

    public function isReply(): bool
    {
        return $this->isComment() && !is_null($this->parent_id);
    }

    public function hasReplies(): bool
    {
        return $this->replies()->exists();
    }

    public function getMentions(): array
    {
        return $this->metadata['mentions'] ?? [];
    }
    
    /**
     * Get file attachments for this interaction
     */
    public function attachments()
    {
        return $this->hasMany(FileAttachment::class, 'interaction_id');
    }

    public function setMentions(array $mentions)
    {
        $this->metadata = array_merge($this->metadata ?? [], ['mentions' => $mentions]);
    }

    // Invitation Methods
    public function scopeInvitations($query)
    {
        return $query->where('type', 'invitation');
    }

    public function scopePendingInvitations($query)
    {
        return $query->where('type', 'invitation')->where('status', 'pending');
    }

    public function isInvitation(): bool
    {
        return $this->type === 'invitation';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function accept()
    {
        $this->update(['status' => 'accepted']);
        
        // Create assignment if this is an invitation
        if ($this->isInvitation()) {
            self::create([
                'task_id' => $this->task_id,
                'user_id' => $this->user_id,
                'type' => 'assignment',
                'role' => $this->role,
                'status' => 'accepted',
            ]);
        }
    }

    public function decline()
    {
        $this->update(['status' => 'declined']);
    }

    // Reminder Methods have been removed

    // Notification Methods
    public function scopeNotifications($query)
    {
        return $query->where('type', 'notification');
    }

    public function scopeUnreadNotifications($query)
    {
        return $query->where('type', 'notification')->where('status', 'unread');
    }

    public function isNotification(): bool
    {
        return $this->type === 'notification';
    }

    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    public function isUnread(): bool
    {
        return $this->status === 'unread';
    }

    public function markAsRead()
    {
        $this->update(['status' => 'read']);
    }

    public function markAsUnread()
    {
        $this->update(['status' => 'unread']);
    }

    // Static helper methods
    public static function createAssignment($taskId, $userId, $role = 'assignee')
    {
        return self::create([
            'task_id' => $taskId,
            'user_id' => $userId,
            'type' => 'assignment',
            'role' => $role,
            'status' => 'accepted',
        ]);
    }

    public static function createComment($taskId, $userId, $content, $parentId = null, $mentions = [])
    {
        return self::create([
            'task_id' => $taskId,
            'user_id' => $userId,
            'type' => 'comment',
            'content' => $content,
            'parent_id' => $parentId,
            'metadata' => ['mentions' => $mentions],
            'status' => 'accepted',
        ]);
    }

    public static function createInvitation($taskId, $invitedBy, $invitedUserId, $role, $message = null)
    {
        return self::create([
            'task_id' => $taskId,
            'user_id' => $invitedUserId,
            'type' => 'invitation',
            'role' => $role,
            'content' => $message,
            'metadata' => ['invited_by' => $invitedBy],
            'status' => 'pending',
        ]);
    }

    public static function createNotification($userId, $taskId, $title, $message, $type = 'general', $metadata = [])
    {
        return self::create([
            'task_id' => $taskId,
            'user_id' => $userId,
            'type' => 'notification',
            'content' => $message,
            'metadata' => array_merge($metadata, ['title' => $title, 'notification_type' => $type]),
            'status' => 'unread',
        ]);
    }
    
    // createReminder method has been removed

    // Mention extraction
    public static function extractMentions($text): array
    {
        preg_match_all('/@(\w+)/', $text, $matches);
        return $matches[1] ?? [];
    }

    public static function getMentionedUserIds($text): array
    {
        $usernames = self::extractMentions($text);
        return \App\Models\User::whereIn('name', $usernames)->pluck('id')->toArray();
    }
}
