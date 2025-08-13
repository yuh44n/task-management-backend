<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    /**
     * Get the user who created this task
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get users assigned to this task
     */
    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'task_interactions')
                    ->wherePivot('type', 'assignment')
                    ->wherePivot('status', 'accepted')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Get task assignments
     */
    public function assignments()
    {
        return $this->hasMany(TaskInteraction::class)->assignments();
    }

    /**
     * Get comments for this task
     */
    public function comments()
    {
        return $this->hasMany(TaskInteraction::class)->comments();
    }

    /**
     * Get top-level comments for this task
     */
    public function topLevelComments()
    {
        return $this->hasMany(TaskInteraction::class)->topLevelComments();
    }

    /**
     * Get invitations for this task
     */
    public function invitations()
    {
        return $this->hasMany(TaskInteraction::class)->invitations();
    }

    /**
     * Get pending invitations for this task
     */
    public function pendingInvitations()
    {
        return $this->hasMany(TaskInteraction::class)->pendingInvitations();
    }

    /**
     * Get accepted invitations for this task
     */
    public function acceptedInvitations()
    {
        return $this->hasMany(TaskInteraction::class)->invitations()->where('status', 'accepted');
    }

    /**
     * Get declined invitations for this task
     */
    public function declinedInvitations()
    {
        return $this->hasMany(TaskInteraction::class)->invitations()->where('status', 'declined');
    }

    /**
     * Get all interactions for this task
     */
    public function interactions()
    {
        return $this->hasMany(TaskInteraction::class);
    }
    
    /**
     * Get file attachments for this task
     */
    public function attachments()
    {
        return $this->hasMany(FileAttachment::class);
    }

    /**
     * Check if user is assigned to this task
     */
    public function isAssignedTo($userId): bool
    {
        return $this->assignedUsers()->where('user_id', $userId)->exists();
    }

    /**
     * Check if user can edit this task
     */
    public function canEdit($userId): bool
    {
        return $this->created_by === $userId || 
               $this->assignedUsers()->where('user_id', $userId)->whereIn('role', ['assignee', 'collaborator'])->exists();
    }

    /**
     * Check if user can view this task
     */
    public function canView($userId): bool
    {
        return $this->created_by === $userId || 
               $this->assignedUsers()->where('user_id', $userId)->exists();
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to filter by user assignments
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->whereHas('assignedUsers', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Scope to filter by creator
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && now()->greaterThan($this->due_date) && !$this->isCompleted();
    }
}